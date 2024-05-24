<?php

namespace Sixgweb\AttributizeUsers\Classes;

use October\Rain\Database\Model;
use Sixgweb\AttributizeUsers\Classes\Helper;
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

    protected function getComponentClass(): string|array
    {
        if (Helper::getUserPluginVersion() < 3) {
            return \RainLab\User\Components\Account::class;
        }

        return [
            \RainLab\User\Components\Account::class,
            \RainLab\User\Components\Registration::class,
        ];
    }

    protected function getControllerClass(): string
    {
        return \RainLab\User\Controllers\Users::class;
    }

    protected function getComponentModel($component): Model
    {
        if ($component instanceof \RainLab\User\Components\Account) {
            return $component->user() ?? new ($this->getModelClass())();
        }
        return new ($this->getModelClass())();
    }

    protected function getBackendMenuParameters(): array
    {
        return [
            'owner' => 'RainLab.User',
            'code' => 'user',
            'url' => \Backend::url('rainlab/user/users/fields'),
        ];
    }

    protected function getAllowCreateFileUpload(): bool
    {
        return true;
    }
}
