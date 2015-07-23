<?php

use Vinelab\Mailer\Postman;
use Mockery as M;

class PostmanTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->mFilesystem = M::mock('Illuminate\Filesystem\Filesystem');
        $this->mFileLoader = M::mock('Illuminate\Config\Fileloader');
        $this->mConfig = M::mock('Illuminate\Config\Repository');
        $this->mSwift_Transport = M::mock('Swift_Transport');

        $this->smtpConfiguration = array(
            'driver' => 'smtp',
            'host' => 'smtp.host.com',
            'port' => 25,
            'encryption' => 'tls',
            'username' => 'app@host.com',
            'password' => 'My@ppP@$$w0rd',
        );

        $this->sendmailConfiguration = array('driver' => 'sendmail', 'sendmail' => '/usr/sbin/sendmail -bs');

        $this->mConfig->shouldReceive('get')->once()->andReturn(array('driver' => 'mail'));
        $this->postman = new Postman($this->mFilesystem, $this->mFileLoader, $this->mConfig);
        $this->assertInstanceOf('Vinelab\Mailer\Postman', $this->postman);
    }

    public function test_mail_configuration()
    {
        $this->assertEquals('mail', $this->postman->driver);
    }

    public function test_smtp_configuration()
    {
        $conf = $this->smtpConfiguration;

        $this->mConfig->shouldReceive('get')->with('mail')->once()
            ->andReturn($conf);

        $postman = new Postman($this->mFilesystem, $this->mFileLoader, $this->mConfig);

        // make sure configuration was correctly set
        $this->assertEquals($conf['driver'], $postman->driver, 'Configuration should have set the "driver"');
        $this->assertEquals($conf['host'], $postman->host, 'Configuration should have set the "host"');
        $this->assertEquals($conf['port'], $postman->port, 'Configuration should have set the "port"');
        $this->assertEquals($conf['encryption'], $postman->encryption, 'Configuration should have set the "encryption"');
        $this->assertEquals($conf['username'], $postman->username, 'Configuration should have set the "username"');
        $this->assertEquals($conf['password'], $postman->password, 'Configuration should have set the "password"');
    }

    public function test_sendmail_configuration()
    {
        $conf = $this->sendmailConfiguration;
        $this->mConfig->shouldReceive('get')->with('mail')->once()->andReturn($conf);

        $postman = new Postman($this->mFilesystem, $this->mFileLoader, $this->mConfig);
        $this->assertEquals($conf['driver'], $postman->driver);
        $this->assertEquals($conf['sendmail'], $postman->sendmail_command);
    }

    public function test_mail_transport_setup()
    {
        $this->assertInstanceOf('Swift_MailTransport', $this->postman->transport);
    }

    public function test_smpt_transport_setup()
    {
        $this->mConfig->shouldReceive('get')->with('mail')->andReturn($this->smtpConfiguration);

        $postman = new Postman($this->mFilesystem, $this->mFileLoader, $this->mConfig);
        $this->assertInstanceOf('Swift_SmtpTransport', $postman->transport);
    }

    public function test_sendmail_transport_setup()
    {
        $this->mConfig->shouldReceive('get')->with('mail')->andReturn($this->sendmailConfiguration);

        $postman = new Postman($this->mFilesystem, $this->mFileLoader, $this->mConfig);
        $this->assertInstanceOf('Swift_SendmailTransport', $postman->transport);
    }

    public function test_enclosing_message()
    {
        $message = array(
            'from' => 'john@doe.net',
            'to' => 'jannet@dublin.com',
            'subject' => 'Aloha!',
            'body' => 'wanna cyber ?',
            'content_type' => 'text/html',
        );

        $enclose = static::getProtectedMethod('enclose', $this->postman);
        $enclosed_message = $enclose->invokeArgs($this->postman, array($message));

        $this->assertInstanceOf('Swift_Message', $enclosed_message);
        $this->assertArrayHasKey($message['from'], $enclosed_message->getFrom(), 'Should have set From');
        $this->assertArrayHasKey($message['to'], $enclosed_message->getTo(), 'Should have set To');
        $this->assertEquals($message['subject'], $enclosed_message->getSubject(), 'Should have set Subject');
        $this->assertEquals($message['body'], $enclosed_message->getBody(), 'Should have set Body');
    }

    protected static function getProtectedMethod($name, $class)
    {
        $class = new \ReflectionClass(get_class($class));
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }
}
