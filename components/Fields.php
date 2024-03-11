<?php

namespace Sixgweb\AttributizeUsers\Components;

use Event;
use Sixgweb\Attributize\Components\Fields as FieldsBase;

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
        Event::listen('rainlab.user.beforeRegister', function (&$data) {
            $column = $this->model->fieldableGetColumn();
            $data[$column] = $this->model->{$column};
        });
    }
}
