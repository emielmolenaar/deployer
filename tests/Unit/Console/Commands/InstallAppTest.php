<?php

namespace REBELinBLUE\Deployer\Tests\Unit\Console\Commands;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\KeyGenerateCommand;
use Illuminate\Foundation\Console\OptimizeCommand;
use Mockery as m;
use phpmock\mockery\PHPMockery as phpm;
use REBELinBLUE\Deployer\Console\Commands\InstallApp;
use REBELinBLUE\Deployer\Console\Commands\Installer\EnvFile;
use REBELinBLUE\Deployer\Console\Commands\Installer\Requirements;
use REBELinBLUE\Deployer\Services\Filesystem\Filesystem;
use REBELinBLUE\Deployer\Services\Token\TokenGenerator;
use REBELinBLUE\Deployer\Tests\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @coversDefaultClass \REBELinBLUE\Deployer\Console\Commands\InstallApp
 */
class InstallAppTest extends TestCase
{
    protected $filesystem;
    private $console;
    private $config;
    private $generator;
    private $requirements;
    private $laravel;
    private $env;

    public function setUp()
    {
        parent::setUp();

        $console = m::mock(ConsoleApplication::class)->makePartial();
        $console->__construct();

        $this->console      = $console;
        $this->requirements = m::mock(Requirements::class);
        $this->config       = m::mock(ConfigRepository::class);
        $this->filesystem   = m::mock(Filesystem::class);
        $this->generator    = m::mock(TokenGenerator::class);
        $this->env          = m::mock(EnvFile::class);
        $this->laravel      = m::mock(Application::class)->makePartial();

        $this->laravel->shouldReceive('make')->andReturnUsing(function ($arg) {
            return $this->app->make($arg);
        });
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::verifyNotInstalled
     */
    public function testVerifyNotInstalled()
    {
        $this->config->shouldReceive('get')->with('app.key')->andReturn('an-existing-key');

        $tester = $this->runCommand();
        $output = $tester->getDisplay();

        $this->assertContains('already installed Deployer', $output);
        $this->assertContains('php artisan app:update', $output);
        $this->assertSame(-1, $tester->getStatusCode());
    }

    /**
     * @covers ::__construct
     * @covers ::handle
     * @covers ::verifyNotInstalled
     */
    public function testCheckRequirements()
    {
        $this->config->shouldReceive('get')->with('app.key')->andReturn(false);
        $this->requirements->shouldReceive('check')->with(m::type(InstallApp::class))->andReturn(false);

        $tester = $this->runCommand();

        $this->assertSame(-1, $tester->getStatusCode());
    }

    /**
     * @covers ::<public>
     * @covers ::<protected>
     * @covers ::<private>
     */
    public function testHandleSuccessful()
    {
        $this->config->shouldReceive('get')->with('app.key')->andReturn(false);
        $this->requirements->shouldReceive('check')->with(m::type(InstallApp::class))->andReturn(true);

        $command = m::mock(Command::class);
        $command->shouldReceive('run');

        $key = m::mock(KeyGenerateCommand::class);
        $key->shouldReceive('run')->with(m::on(function (ArrayInput $arg) {
            $this->assertTrue($arg->getParameterOption('--force'));

            return true;
        }), m::any());

        $optimize = m::mock(OptimizeCommand::class);
        $optimize->shouldReceive('run')->with(m::on(function (ArrayInput $arg) {
            $this->assertTrue($arg->getParameterOption('--force'));

            return true;
        }), m::any());

        $this->console->shouldReceive('find')->times(2)->with('clear-compiled')->andReturn($command);
        $this->console->shouldReceive('find')->times(2)->with('cache:clear')->andReturn($command);
        $this->console->shouldReceive('find')->times(2)->with('route:clear')->andReturn($command);
        $this->console->shouldReceive('find')->times(2)->with('config:clear')->andReturn($command);
        $this->console->shouldReceive('find')->times(2)->with('view:clear')->andReturn($command);
        $this->console->shouldReceive('find')->once()->with('key:generate')->andReturn($key);
        $this->console->shouldReceive('find')->once()->with('optimize')->andReturn($optimize);
        $this->console->shouldReceive('find')->once()->with('config:cache')->andReturn($command);
        $this->console->shouldReceive('find')->once()->with('route:cache')->andReturn($command);

        $env           = base_path('.env');
        $dist          = base_path('.env.dist');
        $expectedToken = 'a-random-app-key';

        $this->filesystem->shouldReceive('exists')->with($env)->andReturn(false);
        $this->filesystem->shouldReceive('copy')->with($dist, $env);
        $this->config->shouldReceive('set')->with('app.key', 'SomeRandomString');
        $this->laravel->shouldReceive('environment')->with('local')->andReturn(false);

        // PHP drivers
        phpm::mock('REBELinBLUE\Deployer\Console\Commands\Traits', 'pdo_drivers')->andReturn(['sqlite', 'mysql']);

        $this->filesystem->shouldReceive('touch')->with(database_path('database.sqlite'))->andReturn(true);
        $this->generator->shouldReceive('generateRandom')->andReturn($expectedToken);

        $tester = $this->runCommand($this->laravel, [
            // Database details
            'sqlite',
//            'localhost', // Currently can't mock PDO
//            3306,
//            'deployer',
//            'deployer',
//            'secret'

            // Hipchat
            'yes',
            'http://hooks.hipchat.com',
            'a-hipchat-token',

            // Twilio
            'yes',
            'twilio-sid',
            'twilio-token',
            '+44770812345678',

            // Mail
            'sendmail',
            'Deployer',
            'deployer@example.com',

            // Admin details
            'Admin',
            'admin@example.com',
            'password',
        ]);
        $output = $tester->getDisplay();

        //$this->assertContains('failed!', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    private function runCommand($app = null, array $inputs = [])
    {
        $command = new InstallApp(
            $this->config,
            $this->filesystem,
            $this->generator,
            $this->requirements,
            $this->env
        );

        $command->setLaravel($app ?: $this->app);
        $command->setApplication($this->console);

        $tester = new CommandTester($command);

        try {
            $tester->setInputs($inputs);
            $tester->execute([
                'command' => 'app:install',
            ]);
        } catch (\Exception $error) {
            dd($error);
        }

        return $tester;
    }
}
