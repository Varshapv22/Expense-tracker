<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('expenses', 'transactions');

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('expense_date', 'transaction_date');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('type')->default('expense')->after('user_id');
            $table->foreignId('account_id')->nullable()->after('type')->constrained('accounts')->nullOnDelete();
            $table->foreignId('to_account_id')->nullable()->after('account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->after('to_account_id')->constrained('categories')->nullOnDelete();
        });

        // Backfill: every user with existing transactions gets a default
        // "Cash" account, and their existing rows are pointed at it.
        $userIds = DB::table('transactions')->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            $accountId = DB::table('accounts')->insertGetId([
                'user_id' => $userId,
                'name' => 'Cash',
                'type' => 'cash',
                'opening_balance' => 0,
                'currency' => 'INR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('transactions')
                ->where('user_id', $userId)
                ->whereNull('account_id')
                ->update(['account_id' => $accountId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['to_account_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['type', 'account_id', 'to_account_id', 'category_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('transaction_date', 'expense_date');
        });

        Schema::rename('transactions', 'expenses');
    }
};
