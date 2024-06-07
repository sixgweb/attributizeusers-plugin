<?php

namespace Sixgweb\AttributizeUsers;

use App;
use Auth;
use Event;
use Schema;
use Backend;
use RainLab\User\Models\User;
use System\Classes\PluginBase;
use Sixgweb\Attributize\Models\Field;
use Sixgweb\Attributize\Models\Settings;
use Sixgweb\AttributizeUsers\Classes\Helper;
use Sixgweb\Attributize\Components\Fields as FieldsComponent;
use Sixgweb\AttributizeUsers\Classes\EventHandler;

/**
 * Plugin Information File
 */
class Plugin extends PluginBase
{

    public $require = [
        'Sixgweb.Attributize',
        'RainLab.User',
    ];

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'AttributizeUsers',
            'description' => 'Attributize RainLab.User plugin',
            'author'      => 'Sixgweb',
            'icon'        => 'icon-user'
        ];
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot()
    {
        Event::subscribe(EventHandler::class);
        $this->extendUserModel();
        $this->extendAttributizeSettings();
        $this->extendUsersController();
        $this->extendRegistrationComponent();
        $this->extendAttributizeWidget();
        $this->extendFieldsComponent();
    }

    /**
     * register plugin components
     *
     * @return array
     */
    public function registerComponents(): array
    {
        return [
            'Sixgweb\AttributizeUsers\Components\Fields' => 'userFields',
        ];
    }

    /**
     * Extends user model, replacing name attribute, if enabled
     * in settings
     *
     * @return void
     */
    protected function extendUserModel()
    {
        if (!Settings::get('user.override_name', false)) {
            return;
        }

        User::extend(function ($model) {

            $model->addDynamicMethod('getFirstNameAttribute', function ($value) use ($model) {
                return $model->name;
            });

            $model->addDynamicMethod('getLastNameAttribute', function ($value) use ($model) {
                return '';
            });

            $model->addDynamicMethod('getNameAttribute', function ($value) use ($model) {
                if ($format = Settings::get('user.fullname')) {
                    $name = '';
                    foreach ($format as $field) {
                        if ($field['code'] == 'name') {
                            $name .= $value;
                        } else {

                            $val = $model->{$field['code']};
                            if (!$val) {
                                $code = str_replace(['field_values[', ']'], '', $field['code']);
                                $val = $model->field_values[$code] ?? null;
                            }

                            if ($val && $val != 'null') {
                                if ($field['comma']) {
                                    $name = trim($name);
                                    $name .= ', ' . $val;
                                } else {
                                    $name .= $val . ' ';
                                }
                            }
                        }
                    }
                    return trim($name);
                } else {
                    return $value;
                }
            });

            $model->bindEvent('model.beforeValidate', function () use ($model) {

                if (Helper::getUserPluginVersion() >= 3) {
                    //User model in v3 has a first_name required rule.  Remove it, if we're overriding.
                    $model->removeValidationRule('first_name', 'required');

                    //Remove hardcoded last_name value in extendRegistrationComponent() 
                    $model->bindEvent('model.beforeCreate', function () use ($model) {
                        $model->last_name = '';
                    });

                    //use the above accessor to update the first_name column
                    $model->first_name = $model->name;
                } else {
                    //use the above accessor to update the name column
                    $model->name = $model->name;
                }
            });
        });
    }

    protected function extendUsersController()
    {
        $nameField = Helper::getUserPluginVersion() >= 3 ? 'first_name' : 'name';
        $surnameField = Helper::getUserPluginVersion() >= 3 ? 'last_name' : 'surname';

        \RainLab\User\Controllers\Users::extend(function ($controller) {

            $controller->addCss('/plugins/sixgweb/attributizeusers/assets/css/attributizeusers.css');

            if (!isset($controller->importExportConfig)) {
                $controller->implement[] = 'Backend.Behaviors.ImportExportController';
                $controller->addDynamicProperty('importExportConfig', [
                    'export' => [
                        'useList' => [
                            'raw' => true,
                        ],
                        'fileName' => 'export-users-' . date('Y-m-d'),
                    ]
                ]);
            }
        });

        if (Settings::get('user.add_export_features')) {

            //Users v3 now has import/export so update the config file if exportUrl was used
            Event::listen('system.extendConfigFile', function ($file, $config) {
                if ($file == '/plugins/rainlab/user/controllers/users/config_import_export.yaml') {
                    if (get('useList')) {
                        $config['export']['useList'] = [
                            'raw' => true,
                        ];
                        return $config;
                    }
                }
            });

            Event::listen('rainlab.user.view.extendListToolbar', function ($controller) {
                return $controller->makePartial(
                    '~/plugins/sixgweb/attributizeusers/partials/_export_button.htm',
                    [
                        'exportUrl' => Backend::url('rainlab/user/users/export?useList=1'),
                    ]
                );
            });
        }

        Event::listen('backend.form.extendFields', function ($widget) use ($nameField, $surnameField) {
            // Only for the User controller
            if (!$widget->getController() instanceof \RainLab\User\Controllers\Users) {
                return;
            }

            // Only for the User model
            if (!$widget->model instanceof \RainLab\User\Models\User) {
                return;
            }

            if (Settings::get('user.override_name')) {
                $widget->removeField($nameField);
                $widget->removeField($surnameField);
            } else {
                if (isset($widget->fields[$nameField]) && Helper::getUserPluginVersion() < 3) {
                    $field = $widget->getField($nameField);
                    $field->comment = 'Note: Name attribute can be disabled in Settings->Attributize';
                }
            }
        }, 10000);

        Event::listen('backend.list.extendColumns', function ($widget) use ($nameField, $surnameField) {
            // Only for the User controller
            if (!$widget->getController() instanceof \RainLab\User\Controllers\Users) {
                return;
            }

            // Only for the User model
            if (!$widget->model instanceof \RainLab\User\Models\User) {
                return;
            }

            if (Settings::get('user.override_name')) {
                $widget->removeColumn($nameField);
                $widget->removeColumn($surnameField);
            }
        }, 10000);
    }

    /**
     * Enables default field values from user profile
     *
     * @return void
     */
    protected function extendFieldsComponent()
    {
        if (!App::runningInBackend()) {
            FieldsComponent::extend(function ($component) {
                $component->bindEvent('fields.getFieldValues', function ($model, &$fieldValues) use ($component) {
                    if (!$model->exists && $user = Auth::getUser()) {
                        $fields = $component->model
                            ->fieldableGetFields()
                            ->pluck('config.prefill', 'code')
                            ->toArray();

                        foreach ($fields as $code => $prefill) {
                            if ($prefill) {
                                if ($value = array_get($user, $prefill)) {
                                    $fieldValues[$code] = $value;
                                }
                            }
                        }
                    }

                    return $fieldValues;
                });
            });
        }
    }

    /**
     * Add Users settings to attributize settings model
     *
     * @return void
     */
    protected function extendAttributizeSettings()
    {
        Event::listen('backend.form.extendFields', function ($form) {

            if (!$form->model instanceof Settings) {
                return;
            }

            //Don't extend repeaters
            if ($form->isNested) {
                return;
            }

            //Get the user fields for the override options
            $user = new User;
            $options = [];
            $fields = $user->fieldableGetFields([
                'useScopes' => false,
                'useGlobalScopes' => false,
            ])->pluck('name', 'code')->toArray();
            foreach ($fields as $code => $name) {
                $options['field_values[' . $code . ']'] = $name;
            }

            $form->addTabFields([
                'user[add_export_features]' => [
                    'label' => 'Enable Export Users Function',
                    'type' => 'checkbox',
                    'comment' => 'If checked, new button added to Users list for exporting users',
                    'tab' => 'Users'
                ],
                'user[override_name]' => [
                    'label' => 'Override Name Attribute',
                    'type' => 'checkbox',
                    'tab' => 'Users'
                ],
                'user[fullname]' => [
                    'label' => 'Name Attribute Fields',
                    'type' => 'repeater',
                    'commentAbove' => 'User fields used to generate the full name value',
                    'trigger' => [
                        'field' => 'user[override_name]',
                        'action' => 'show',
                        'condition' => 'checked',
                    ],
                    'form' => [
                        'fields' => [
                            'code' => [
                                'label' => 'Select Field',
                                'type' => 'dropdown',
                                'span' => 'left',
                                'options' => $options,
                            ],
                            'comma' => [
                                'label' => 'Place Comma Before Value',
                                'type' => 'checkbox',
                                'span' => 'right',
                            ]
                        ]
                    ],
                    'tab' => 'Users',
                ]
            ]);
        });
    }

    /**
     * Adds dropdown to select default value from user fields
     * in other integration's fields
     *
     * @return void
     */
    protected function extendAttributizeWidget()
    {
        Event::listen('backend.form.extendFields', function ($widget) {
            if (
                !$widget->model instanceof \Sixgweb\Attributize\Models\Field
                || $widget->getController() instanceof \RainLab\User\Controllers\Users
                || $widget->isNested
                || $widget->context != 'attributize'
            ) {
                return;
            }

            $fields = Field::where('fieldable_type', 'RainLab\User\Models\User')
                ->frontend()
                ->enabled()
                ->get()
                ->pluck('name', 'code')
                ->toArray();

            foreach ($fields as $code => $name) {
                $fields['field_values.' . $code] = $name;
                unset($fields[$code]);
            }
            $fields = ['email' => 'Email (account)', 'fullname' => 'Full Name (account)'] + $fields;


            $widget->addTabFields([
                'config[prefill]' => [
                    'label' => 'Default User Value',
                    'comment' => 'Prefill this field with a user profile field value',
                    'tab' => 'sixgweb.attributize::lang.field.field',
                    'emptyOption' => '-- default value --',
                    'type' => 'dropdown',
                    'options' => $fields,
                    'context' => [
                        'attributize',
                    ]
                ]
            ]);
        });
    }

    /**
     * Gross hack.  RainLab.User v3 requires first_name and last_name in the registration component onRegister validation check.  If we're overriding the name attribute, set first/last to the email to pass validation.
     * 
     * Our beforeValidate event listener above will then set the correct values before the model is saved.
     * @return void
     */
    protected function extendRegistrationComponent()
    {
        if (Helper::getUserPluginVersion() < 3) {
            return;
        }

        Event::listen('rainlab.user.beforeRegister', function ($component, &$input) {
            if (Settings::get('user.override_name')) {
                $input['first_name'] = $input['email'];
                $input['last_name'] = $input['email'];
            }
        });
    }
}
