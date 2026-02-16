<?php

declare(strict_types=1);

namespace Tests;

use Koldy\Config;
use Koldy\Config\Exception as ConfigException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{

	private array $tempFiles = [];

	protected function tearDown(): void
	{
		foreach ($this->tempFiles as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
		$this->tempFiles = [];
	}

	private function createTempConfigFile(array $data): string
	{
		$path = tempnam(sys_get_temp_dir(), 'koldy_config_test_');
		$this->tempFiles[] = $path;
		file_put_contents($path, '<?php return ' . var_export($data, true) . ';');
		return $path;
	}

	// ── constructor / name / isPointerConfig / __toString ──

	public function testConstructorSetsName(): void
	{
		$config = new Config('myconfig');
		$this->assertSame('myconfig', $config->name());
	}

	public function testConstructorDefaultNotPointerConfig(): void
	{
		$config = new Config('myconfig');
		$this->assertFalse($config->isPointerConfig());
	}

	public function testConstructorPointerConfig(): void
	{
		$config = new Config('myconfig', true);
		$this->assertTrue($config->isPointerConfig());
	}

	public function testToString(): void
	{
		$config = new Config('database');
		$this->assertSame('Config name=database', (string)$config);
	}

	// ── set / get / has / delete ──

	public function testSetAndGet(): void
	{
		$config = new Config('test');
		$config->set('key1', 'value1');
		$config->set('key2', 42);
		$this->assertSame('value1', $config->get('key1'));
		$this->assertSame(42, $config->get('key2'));
	}

	public function testGetReturnsNullForMissingKey(): void
	{
		$config = new Config('test');
		$config->setData(['a' => 1]);
		$this->assertNull($config->get('nonexistent'));
	}

	public function testHasReturnsTrueForExistingKey(): void
	{
		$config = new Config('test');
		$config->setData(['key' => 'value']);
		$this->assertTrue($config->has('key'));
	}

	public function testHasReturnsFalseForMissingKey(): void
	{
		$config = new Config('test');
		$config->setData(['key' => 'value']);
		$this->assertFalse($config->has('other'));
	}

	public function testDelete(): void
	{
		$config = new Config('test');
		$config->setData(['a' => 1, 'b' => 2]);
		$config->delete('a');
		$this->assertFalse($config->has('a'));
		$this->assertTrue($config->has('b'));
	}

	public function testDeleteNonExistentKeyIsSafe(): void
	{
		$config = new Config('test');
		$config->setData(['a' => 1]);
		$config->delete('nonexistent');
		$this->assertTrue($config->has('a'));
	}

	// ── setData / getData / hasData ──

	public function testSetDataAndGetData(): void
	{
		$config = new Config('test');
		$data = ['host' => 'localhost', 'port' => 3306];
		$config->setData($data);
		$this->assertSame($data, $config->getData());
	}

	public function testHasDataReturnsFalseWhenEmpty(): void
	{
		$config = new Config('test');
		$this->assertFalse($config->hasData());
	}

	public function testHasDataReturnsTrueWhenSet(): void
	{
		$config = new Config('test');
		$config->setData(['key' => 'value']);
		$this->assertTrue($config->hasData());
	}

	public function testGetDataThrowsWhenNotLoaded(): void
	{
		$this->expectException(ConfigException::class);
		$config = new Config('test');
		$config->getData();
	}

	// ── get / has throw when not loaded ──

	public function testGetThrowsWhenNotLoaded(): void
	{
		$this->expectException(ConfigException::class);
		$config = new Config('test');
		$config->get('key');
	}

	public function testHasThrowsWhenNotLoaded(): void
	{
		$this->expectException(ConfigException::class);
		$config = new Config('test');
		$config->has('key');
	}

	// ── loadFrom ──

	public function testLoadFromLoadsPhpFile(): void
	{
		$path = $this->createTempConfigFile(['db' => 'mysql', 'port' => 3306]);
		$config = new Config('database');
		$config->loadFrom($path);
		$this->assertSame('mysql', $config->get('db'));
		$this->assertSame(3306, $config->get('port'));
	}

	public function testLoadFromThrowsForMissingFile(): void
	{
		$this->expectException(ConfigException::class);
		$config = new Config('test');
		$config->loadFrom('/nonexistent/path/config.php');
	}

	public function testLoadFromThrowsForNonArrayReturn(): void
	{
		$path = tempnam(sys_get_temp_dir(), 'koldy_config_test_');
		$this->tempFiles[] = $path;
		file_put_contents($path, '<?php return "not an array";');

		$this->expectException(ConfigException::class);
		$config = new Config('test');
		$config->loadFrom($path);
	}

	// ── reload ──

	public function testReloadReloadsFromFile(): void
	{
		$path = $this->createTempConfigFile(['version' => 1]);
		$config = new Config('test');
		$config->loadFrom($path);
		$this->assertSame(1, $config->get('version'));

		// Update the file
		file_put_contents($path, '<?php return ' . var_export(['version' => 2], true) . ';');
		$config->reload();
		$this->assertSame(2, $config->get('version'));
	}

	public function testReloadDoesNothingWhenNotLoadedFromFile(): void
	{
		$config = new Config('test');
		$config->setData(['key' => 'value']);
		$config->reload(); // should not throw
		$this->assertSame('value', $config->get('key'));
	}

	// ── isOlderThan ──

	public function testIsOlderThanReturnsFalseForFreshConfig(): void
	{
		$config = new Config('test');
		$config->setData(['key' => 'value']);
		$this->assertFalse($config->isOlderThan(10));
	}

	public function testIsOlderThanThrowsWhenNotLoaded(): void
	{
		$this->expectException(ConfigException::class);
		$config = new Config('test');
		$config->isOlderThan(10);
	}

	// ── getFullPath ──

	public function testGetFullPathReturnsNullWhenNotLoadedFromFile(): void
	{
		$config = new Config('test');
		$this->assertNull($config->getFullPath());
	}

	public function testGetFullPathReturnsPathWhenLoadedFromFile(): void
	{
		$path = $this->createTempConfigFile(['key' => 'value']);
		$config = new Config('test');
		$config->loadFrom($path);
		$this->assertSame($path, $config->getFullPath());
	}

	// ── getArrayItem ──

	public function testGetArrayItemReturnsSubKey(): void
	{
		$config = new Config('test');
		$config->setData([
			'database' => ['host' => 'localhost', 'port' => 3306],
		]);
		$this->assertSame('localhost', $config->getArrayItem('database', 'host'));
		$this->assertSame(3306, $config->getArrayItem('database', 'port'));
	}

	public function testGetArrayItemReturnsNullForMissingSubKey(): void
	{
		$config = new Config('test');
		$config->setData([
			'database' => ['host' => 'localhost'],
		]);
		$this->assertNull($config->getArrayItem('database', 'nonexistent'));
	}

	public function testGetArrayItemThrowsWhenKeyIsNotArray(): void
	{
		$this->expectException(ConfigException::class);
		$config = new Config('test');
		$config->setData(['key' => 'string_value']);
		$config->getArrayItem('key', 'sub');
	}

	// ── getFirstKey ──

	public function testGetFirstKeyReturnsFirstKey(): void
	{
		$config = new Config('test');
		$config->setData(['first' => 'a', 'second' => 'b']);
		$this->assertSame('first', $config->getFirstKey());
	}

	public function testGetFirstKeyThrowsWhenEmpty(): void
	{
		$this->expectException(ConfigException::class);
		$config = new Config('test');
		$config->setData([]);
		$config->getFirstKey();
	}

	// ── checkPresence ──

	public function testCheckPresencePassesWhenAllKeysPresent(): void
	{
		$config = new Config('test');
		$config->setData(['a' => 1, 'b' => 2, 'c' => 3]);
		$missing = $config->checkPresence(['a', 'b'], false);
		$this->assertEmpty($missing);
	}

	public function testCheckPresenceThrowsWhenKeysMissing(): void
	{
		$this->expectException(ConfigException::class);
		$config = new Config('test');
		$config->setData(['a' => 1]);
		$config->checkPresence(['a', 'b', 'c']);
	}

	public function testCheckPresenceReturnsMissingKeysWithoutThrowing(): void
	{
		$config = new Config('test');
		$config->setData(['a' => 1]);
		$missing = $config->checkPresence(['a', 'b', 'c'], false);
		$this->assertSame(['b', 'c'], $missing);
	}

	// ── pointer config ──

	public function testPointerConfigFollowsRedirects(): void
	{
		$config = new Config('test', true);
		$config->setData([
			'default' => 'primary',
			'primary' => ['host' => 'localhost'],
		]);
		$result = $config->get('default');
		$this->assertSame(['host' => 'localhost'], $result);
	}

	public function testPointerConfigTooManyRedirectsThrows(): void
	{
		$this->expectException(ConfigException::class);
		$config = new Config('test', true);
		$config->setData([
			'a' => 'b',
			'b' => 'c',
			'c' => 'd',
			'd' => 'e',
			'e' => 'f',
			'f' => 'g',
			'g' => 'h',
			'h' => 'i',
			'i' => 'j',
			'j' => 'k',
			'k' => 'l',
		]);
		$config->get('a');
	}

	public function testPointerConfigGetFirstKeyFollowsRedirects(): void
	{
		$config = new Config('test', true);
		$config->setData([
			'default' => 'primary',
			'primary' => ['host' => 'localhost'],
		]);
		// getFirstKey on pointer config follows string redirects to the final non-string key
		$this->assertSame('primary', $config->getFirstKey());
	}

	// ── set initializes data when null ──

	public function testSetInitializesDataWhenNull(): void
	{
		$config = new Config('test');
		$config->set('key', 'value');
		$this->assertSame('value', $config->get('key'));
	}

	public function testSetOverridesExistingKey(): void
	{
		$config = new Config('test');
		$config->setData(['key' => 'old']);
		$config->set('key', 'new');
		$this->assertSame('new', $config->get('key'));
	}
}
