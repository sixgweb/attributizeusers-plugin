<?php

namespace Sixgweb\AttributizeUsers\Components;

use Event;
use Sixgweb\Attributize\Components\Fields as FieldsBase;
use Sixgweb\AttributizeUsers\Classes\Helper;

/**
 * Fields Component
 *
 * @link https://docs.octobercms.com/3.x/extend/cms-components.html
 */
class Fields extends FieldsBase
{
    /**
     * componentDetails
     */
    public function componentDetails()
    {
        return [
            'name' => 'User Fields',
            'description' => 'Display Attributize Fields for RainLab.Users'
        ];
    }

    public function createFormWidget()
    {
        parent::createFormWidget();

        /**
         * At this point, the model is filled with post data in the parent component.
         * However, RainLab.User does not use our filled model, when registering.
         * As a workaround, we add an event lister to the Account component,
         * and modify the data to use our filled model values.
         **/
        if (Helper::getUserPluginVersion() >= 3) {

            /**
             * User v3 uses User::create() instead of new User()->fill()->save().
             * We need to bind to the model.beforeCreate event to fill the model with our data.
             */
            Event::listen('rainlab.user.beforeRegister', function ($component, &$data) {
                $column = $this->model->fieldableGetColumn();
                $data[$column] = $this->model->{$column};
                $modelClass = get_class($this->model);
                $modelClass::extend(function ($model) use ($data) {
                    $model->bindEvent('model.beforeCreate', function () use ($model, $data) {
                        $model->fill($data);
                    });
                    $model->bindEvent('model.beforeValidate', function () use ($model, $data) {
                        $model->fill($data);
                    });
                });
            });
        } else {
            Event::listen('rainlab.user.beforeRegister', function (&$data) {
                $column = $this->model->fieldableGetColumn();
                $data[$column] = $this->model->{$column};
            });
        }
    }
}
