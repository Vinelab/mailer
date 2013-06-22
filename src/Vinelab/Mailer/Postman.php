<?php namespace Vinelab\Mailer;

use Vinelab\Mailer\Exceptions\MailTransportException;

use Illuminate\Config\FileLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Config\Repository as Config;

use Swift_Message;
use Swift_MailTransport;
use Swift_SendmailTransport;
use Swift_SmtpTransport;
use Swift_Mailer;


Class Postman {

	/**
	 * Mail driver
	 *
	 * @var string
	 */
	protected $driver;

	/**
	 * SMTP host address
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * SMTP host port
	 *
	 * @var integer
	 */
	protected $port;

	/**
	 * Mail encryption (ssl, tls, etc.)
	 * @var string
	 */
	protected $encryption;

	/**
	 * SMTP server username
	 *
	 * @var string
	 */
	protected $username;

	/**
	 * SMTP server password
	 *
	 * @var string
	 */
	protected $password;

	/**
	 * The mail transport to use when sending mail
	 *
	 * @var \Swift_MailTransport | Swift_SmtpMailTransport | Swift_SendmailTransport
	 */
	protected $transport;

	/**
	 * The mail being sent
	 *
	 * @var Swift_Mailer
	 */
	protected $mail;

	/**
	 * The environment the mail should run under
	 *
	 * @var string
	 */
	public $environment;

	/**
	 * Mail sending status
	 * @var boolean
	 */
	public $status = false;

	public function __construct($environment = 'local')
	{
		$this->environment = $environment;
		$this->configure()->setUpTransport();
	}

	/**
	 * Instantiates and sends a Postman mail instance
	 *
	 * @param  string|array $from   sender email address OR [email=>name]
	 * @param  string|array $to    	recipient email address OR [email=>name]
	 * @param  string $subject
	 * @param  string $body
	 * @param  string $content_type
	 * @return \Vinelab\Mailer\Postman
	 */
	public function send($from, $to, $subject, $body, $content_type = 'text/html')
	{
		return $this->transmit(compact('from', 'to', 'subject', 'body', 'content_type'));
	}

	public function mailStatus()
	{
		return $this->mail;
	}

	/**
	 * Sets the configuration parameters of this mail instance
	 * from a config or [ENVIRONMENT]/config directory
	 *
	 * @return \Vinelab\Mailer\Postman
	 */
	protected function configure()
	{
		$filesystem = new FileSystem();
		$fileloader = new Fileloader($filesystem, $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'config');
		$config = new Config($fileloader, $this->environment);

		$mail = (object) $config->get('mail');
		$this->driver = $mail->driver;

		if ($this->driver == 'smtp')
		{
			$this->host       = $mail->host;
			$this->port       = $mail->port;
			$this->encryption = $mail->encryption;
			$this->username   = $mail->username;
			$this->password   = $mail->password;
		}

		return $this;
	}

	/**
	 * Prepares the transport for delivery
	 * instantiates a Swift Mailer Transport of different types and
	 * sets @var $this->transport
	 * has support for "mail", "sendmail", "smtp"
	 *
	 * @return  \Vinelab\Mailer\Postman
	 */
	protected function setUpTransport()
	{
		switch($this->driver)
		{
			case 'mail':
				$this->transport = Swift_MailTransport::newInstance();
			break;

			case 'smtp':
				$this->transport = Swift_SmtpTransport::newInstance($this->host, $this->port, $this->encryption)
					->setUsername($this->username)
					->setPassword($this->password);
			break;

			case 'sendmail':
				$this->transport = Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs');
			break;

			default:
				throw new MailTransportException('Unrecognized Mail Driver');
			break;
		}

		return $this;
	}

	/**
	 * Encloses a message into a Swift_Message
	 *
	 * @param  array $message
	 * @return Swift_Message
	 */
	protected function enclose($message)
	{
		return Swift_Message::newInstance()
					->setSubject($message['subject'])
					->setFrom($message['from'])
					->setTo($message['to'])
					->setBody($message['body'], $message['content_type']);
	}

	/**
	 * Transmits a mail
	 *
	 * @param  array $message
	 * @return \Vinelab\Mailer\Postman
	 */
	protected function transmit($message)
	{

		$this->status = $this->getMail()->send($this->enclose($message));
		return $this;
	}

	/**
	 * Lazy instantiates a Swift_Mailer instance
	 *
	 * @return Swift_Mailer
	 */
	public function getMail()
	{
		return (!$this->mail instanceof Swift_Mailer) ? $this->mail = Swift_Mailer::newInstance($this->transport) : $this->mail;
	}
}