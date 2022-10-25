<?php

namespace Sixgweb\Attributize\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use RainLab\User\Models\User;

class AddFieldValuesToUsersTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumns('users', ['field_values'])) {
            return;
        }

        Schema::table('users', function ($table) {
            $table->json('field_values')->nullable();
        });
    }

    public function down()
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function ($table) {
            $user = new User;
            foreach ($user->getFieldableFieldsWithoutGlobalScopes() as $field) {
                $field->deleteVirtualColumn();
            }
            if (Schema::hasColumn($table->getTable(), 'field_values')) {
                $table->dropColumn(['field_values']);
            }
        });
    }
}
