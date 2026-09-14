<?php

namespace Tests\Feature;

use App\Models\ChannelTenant;
use Tests\TestCase;

class ChannelConfigTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		config(['database.default' => 'sqlite']);
		config(['database.connections.sqlite.database' => ':memory:']);
		\Illuminate\Support\Facades\DB::purge('sqlite');
		\Illuminate\Support\Facades\DB::reconnect('sqlite');
		foreach ([
			'database/migrations/2026_08_29_120000_create_channel_tenants_table.php',
			'database/migrations/2026_08_11_180000_create_mercadolibre_accounts_table.php',
		] as $path) {
			$this->artisan('migrate', ['--database' => 'sqlite', '--path' => $path]);
		}
	}

	public function test_add_new_user_returns_authorization_token()
	{
		$this->postJson('/api/Config/AddNewUser', [
			'Email' => 'linnworks@limmedia.io',
			'UserId' => 'lw-user-1',
		])
			->assertOk()
			->assertJsonPath('Error', null)
			->assertJsonStructure(['AuthorizationToken']);

		$this->assertTrue(ChannelTenant::query()->where('linnworks_user_id', 'lw-user-1')->exists());
	}

	public function test_user_config_returns_site_field()
	{
		$this->postJson('/api/Config/UserConfig', [])
			->assertOk()
			->assertJsonPath('Error', null)
			->assertJsonPath('ConfigItems.0.ConfigItemId', 'Site');
	}

	public function test_configurator_settings_are_public()
	{
		$this->postJson('/api/Listing/GetConfiguratorSettings', [])
			->assertOk()
			->assertJsonPath('Error', null)
			->assertJsonPath('Settings.0.ConfigItemId', 'Condition');
	}

	public function test_config_test_requires_token()
	{
		$this->postJson('/api/Config/ConfigTest', [])
			->assertStatus(401)
			->assertJsonPath('Error', 'Missing AuthorizationToken');
	}

	public function test_save_and_orders_stub_with_token()
	{
		$token = ChannelTenant::generateAuthorizationToken();
		ChannelTenant::create([
			'linnworks_user_id' => 'lw-user-2',
			'linnworks_email' => 'a@b.c',
			'authorization_token' => $token,
			'site_id' => 'MLM',
			'active' => true,
		]);

		$this->postJson('/api/Config/SaveUserConfig', [
			'AuthorizationToken' => $token,
			'ConfigItems' => [['ConfigItemId' => 'Site', 'SelectedValue' => 'CBT']],
		])->assertOk()->assertJsonPath('Error', null);

		$this->postJson('/api/Order/Orders', ['AuthorizationToken' => $token])
			->assertOk()
			->assertJsonPath('Error', null)
			->assertJsonPath('Orders', []);
	}
}
