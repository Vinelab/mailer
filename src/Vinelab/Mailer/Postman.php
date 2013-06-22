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
	 * Holds the names of protected variables that are allowed direct access
	 * This is a convenience instead of introducing a getVar() method
	 *
	 * @var array
	 */
	protected $accessible = array('driver', 'host', 'port', 'username', 'password', 'encryption', 'sendmail_command', 'transport');

	/**
	 * @var Illuminate\Filesystem\Filesystem
	 */
	protected $_Filesystem;

	/**
	 * @var  Illuminate\Config\Fileloader
	 */
	protected $_FileLoader;

	/**
	 * @var Illuminate\Config\Repository
	 */
	protected $_Config;

	/**
	 * @var Swift_Mailer
	 */
	protected $_Swift_Mailer;

	/**
	 * This is an interface implemented by all Swiftmailer Transports
	 *
	 * @var Swift_Transport
	 */
	protected $_Swift_Transport;

	/**
	 * @var Swift_Message
	 */
	protected $_Swift_Message;

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
	 * The sendmail command to run when the driver is 'sendmail'
	 *
	 * @var string
	 */
	protected $sendmail_command;

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

	public function __construct(
		\Illuminate\Filesystem\Filesystem $filesystem = null,
		\Illuminate\Config\Fileloader $fileloader = null,
		\Illuminate\Config\Repository $config = null,
		\Swift_Mailer $swift_mailer = null,
		\Swift_Transport $swift_transport = null,
		\Swift_Message $swift_message = null
	) {
		$this->_Filesystem      = $filesystem;
		$this->_FileLoader      = $fileloader;
		$this->_Config          = $config;
		$this->_Swift_Mailer    = $swift_mailer;
		$this->_Swift_Transport = $swift_transport;
		$this->_Swift_Message   = $swift_message;

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

	/**
	 * Sets the configuration parameters of this mail instance
	 * from a config or [ENVIRONMENT]/config directory
	 *
	 * @return \Vinelab\Mailer\Postman
	 */
	protected function configure()
	{
		$configuration = (object) $this->configuration();
		$this->driver = $configuration->driver;

		if ($this->driver == 'smtp')
		{
			$this->host       = $configuration->host;
			$this->port       = $configuration->port;
			$this->encryption = $configuration->encryption;
			$this->username   = $configuration->username;
			$this->password   = $configuration->password;

		} elseif ($this->driver == 'sendmail'){

			$this->sendmail_command = $configuration->sendmail;
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
				$this->transport = $this->_Swift_Transport ?: Swift_MailTransport::newInstance();
			break;

			case 'smtp':
				$this->transport = $this->_Swift_Transport ?: Swift_SmtpTransport::newInstance($this->host, $this->port, $this->encryption)
					->setUsername($this->username)
					->setPassword($this->password);
			break;

			case 'sendmail':
				$this->transport = $this->_Swift_Transport ?: Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs');
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
		return ($this->mail instanceof Swift_Mailer) ? $this->mail : $this->mail = Swift_Mailer::newInstance($this->transport);
	}

	/**
	 * Loads the configuration file from the file system and returns its contents
	 * @return array
	 */
	public function configuration()
	{
		$filesystem = $this->_Filesystem ?: new FileSystem();
		$fileloader = $this->_FileLoader ?: new Fileloader($filesystem, $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'config');
		$config     = $this->_Config ?: new Config($fileloader, $this->environment);

		return $config->get('mail');
	}

	public function __get($attribute)
	{
		$public_vars = get_class_vars(get_class($this));

		if(isset($public_vars[$attribute]))
		{
			return $this->{$attribute};

		} elseif (in_array($attribute, $this->accessible)) {

			return $this->{$attribute};
		}

		return null;
	}
}