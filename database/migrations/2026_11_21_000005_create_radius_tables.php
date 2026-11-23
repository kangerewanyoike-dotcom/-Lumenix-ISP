<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->text('radius_secret')->nullable();
        });
        Schema::create('radcheck', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('username')->default('');
            $table->text('attribute')->default('');
            $table->string('op', 2)->default('==');
            $table->text('value')->default('');
            $table->index(['username', 'attribute'], 'radcheck_username');
        });
        Schema::create('radreply', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('username')->default('');
            $table->text('attribute')->default('');
            $table->string('op', 2)->default('=');
            $table->text('value')->default('');
            $table->index(['username', 'attribute'], 'radreply_username');
        });
        Schema::create('radusergroup', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('username')->default('');
            $table->text('groupname')->default('');
            $table->integer('priority')->default(0);
            $table->index('username', 'radusergroup_username');
        });
        Schema::create('radgroupcheck', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('groupname')->default('');
            $table->text('attribute')->default('');
            $table->string('op', 2)->default('==');
            $table->text('value')->default('');
            $table->index(['groupname', 'attribute'], 'radgroupcheck_groupname');
        });
        Schema::create('radgroupreply', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('groupname')->default('');
            $table->text('attribute')->default('');
            $table->string('op', 2)->default('=');
            $table->text('value')->default('');
            $table->index(['groupname', 'attribute'], 'radgroupreply_groupname');
        });
        Schema::create('radacct', function (Blueprint $table): void {
            $table->bigIncrements('radacctid');
            $table->text('acctsessionid');
            $table->text('acctuniqueid')->unique();
            $table->text('username')->nullable();
            $table->text('realm')->nullable();
            $table->ipAddress('nasipaddress');
            $table->text('nasportid')->nullable();
            $table->text('nasporttype')->nullable();
            foreach (['acctstarttime', 'acctupdatetime', 'acctstoptime'] as $column) {
                $table->timestampTz($column)->nullable();
            }
            $table->bigInteger('acctinterval')->nullable();
            $table->bigInteger('acctsessiontime')->nullable();
            $table->text('acctauthentic')->nullable();
            $table->text('connectinfo_start')->nullable();
            $table->text('connectinfo_stop')->nullable();
            $table->bigInteger('acctinputoctets')->nullable();
            $table->bigInteger('acctoutputoctets')->nullable();
            $table->text('calledstationid')->nullable();
            $table->text('callingstationid')->nullable();
            $table->text('acctterminatecause')->nullable();
            $table->text('servicetype')->nullable();
            $table->text('framedprotocol')->nullable();
            $table->ipAddress('framedipaddress')->nullable();
            $table->ipAddress('framedipv6address')->nullable();
            $table->ipAddress('framedipv6prefix')->nullable();
            $table->text('framedinterfaceid')->nullable();
            $table->ipAddress('delegatedipv6prefix')->nullable();
            $table->text('class')->nullable();
            $table->index('acctuniqueid', 'radacct_active_session_idx')->whereNull('acctstoptime');
            $table->index(['nasipaddress', 'acctstarttime'], 'radacct_bulk_close')->whereNull('acctstoptime');
            $table->index(['acctstarttime', 'username'], 'radacct_start_user_idx');
            $table->index('class', 'radacct_calss_idx');
        });
        Schema::create('radpostauth', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->text('username');
            $table->text('pass')->nullable();
            $table->text('reply')->nullable();
            $table->text('calledstationid')->nullable();
            $table->text('callingstationid')->nullable();
            $table->timestampTz('authdate')->useCurrent();
            $table->text('class')->nullable();
            $table->index('username', 'radpostauth_username_idx');
            $table->index('class', 'radpostauth_class_idx');
        });
        Schema::create('nas', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('nasname');
            $table->text('shortname');
            $table->text('type')->default('other');
            $table->integer('ports')->nullable();
            $table->text('secret');
            $table->text('server')->nullable();
            $table->text('community')->nullable();
            $table->text('description')->nullable();
            $table->index('nasname', 'nas_nasname');
        });
        Schema::create('nasreload', function (Blueprint $table): void {
            $table->ipAddress('nasipaddress')->primary();
            $table->timestampTz('reloadtime');
        });
        Schema::create('radius_accounting_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->text('acct_unique_id')->unique();
            $table->text('nas_ip_address')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('updated_at_radius')->nullable();
            $table->timestampTz('stopped_at')->nullable();
            $table->unsignedBigInteger('session_seconds')->default(0);
            $table->unsignedBigInteger('input_octets')->default(0);
            $table->unsignedBigInteger('output_octets')->default(0);
            $table->timestampsTz();
            $table->index(['tenant_id', 'customer_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radius_accounting_sessions');
        foreach (['nasreload', 'nas', 'radpostauth', 'radacct', 'radgroupreply', 'radgroupcheck', 'radusergroup', 'radreply', 'radcheck'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('routers', fn (Blueprint $table) => $table->dropColumn('radius_secret'));
    }
};
