<?php

namespace Sixgweb\AttributizeUsers\Classes;

use October\Rain\Database\Model;
use Sixgweb\Attributize\Classes\AbstractEventHandler;

class EventHandler extends AbstractEventHandler
{
    protected function getTitle(): string
    {
        return 'User Field';
    }
    protected function getModelClass(): string
    {
        return \RainLab\User\Models\User::class;
    }

    protected function getComponentClass(): string
    {
        return \RainLab\User\Components\Account::class;
    }

    protected function getControllerClass(): string
    {
        return \RainLab\User\Controllers\Users::class;
    }

    protected function getComponentModel($component): Model
    {
        return $component->user() ?? new ($this->getModelClass())();
    }

    protected function getBackendMenuParameters(): array
    {
        return [
            'owner' => 'RainLab.User',
            'code' => 'user',
            'path' => 'rainlab/user/users',
        ];
    }

    protected function getAllowCreateFileUpload(): bool
    {
        return true;
    }
}
