<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('name', 'auth_token')
            ->delete();
    }

    public function down(): void
    {
        // Revoked credentials must never become valid again.
    }
};
