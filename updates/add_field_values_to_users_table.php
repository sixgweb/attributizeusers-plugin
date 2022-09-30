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
        if (Schema::hasTable('users') && Schema::hasColumns('users', ['field_values'])) {
            Schema::table('users', function ($table) {
                $user = new User;
                foreach ($user->getAllFieldableFields() as $field) {
                    $column = 'field_values_' . $field->code;
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
                $table->dropColumn(['field_values']);
            });
        }
    }
}
