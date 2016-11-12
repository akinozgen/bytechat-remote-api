<?php

	echo (! defined('SEC') ) ? die('No Direct Script Access Allowed') : NULL;

	class ByteChat
	{

		//Properties
		private $contentType = 'text/html';      // Document Type
		private $pageCharset = 'utf-8';          // Document Charset
		private $dumpFolder  = 'logs';           // Chat Logs Directory
		private $membersFile = './members.json'; // Members File Path
		private $roomsFile   = './rooms.json';   // Rooms File Path

		//Public Variables
		public $requested;   // Requested Method Variable
		public $members;     // Members Array Variable
		public $rooms;       // Rooms Array Variable
		public $currentRoom; // Current Room State Array Variable

		//Errors Variables
		private $errorCodes = array(
			'ERR::WUOPWD', // Wrong username or password
			'ERR::UXPECT', // Unexpected Error
			'ERR::USREXS', // User Allready Exists
			'ERR::ROOMXS', // Room Allready Exists
			'ERR::WRNPWD', // Wrong Password
			'ERR::RMNTEX', // Room Doesnt Exists
			'ERR::UNSAVD', // Unsaved (Save Error)
			'ERR::NOTHIN', // Nothing Requested
			'ERR::UNDFND', // Undefined Offset
			'ERR::NOMRRM', // No More Room (not in hell)
			'ERR::USRNTH', // User Doesn't Exists
			'ERR::EMPTRM', // Room Content is Empty
		);
		private $errors     = array(
			'bootstrap'   => null, // Startup Errors
			'login'       => null, // Login Errors
			'register'    => null, // Register Errors
			'createRoom'  => null, // Room Creating Errors
			'enterRoom'   => null, // Enter Room Errors
			'sendMessage' => null, // Send Message Errors
			'getMessages' => null, // Get Messages Errors
			'getRooms'    => null, // Get Rooms Errors
			'getUser'     => null, // Get Specified User Errors
		);

		/**
		 * Bootstrap Function Defines Document Type,
		 * Defines Requested Method Variable
		 * Defines Members Array Variable and
		 * Defines Rooms Array Variable
		 */
		function __construct()
		{
			//Content Type
			header("Content-type: {$this->contentType}; charset={$this->pageCharset}");

			//Members And Rooms File Creating if it's not exists
			if ( ! file_exists($this->membersFile) )
				touch($this->membersFile);
			if ( ! file_exists($this->roomsFile) )
				touch($this->roomsFile );

			//if process requests exists eq this->requested
			if ( $this->get('p') )
			{
				$this->requested = $this->get('p');
			}
			else
			{
				$this->requested  = false;
				$this->errors['bootstrap'] = $this->errorCodes[7];
			}

			//Bootstrapping Members
			$this->members = json_decode( file_get_contents( $this->membersFile ) );

			//Bootstrapping Rooms
			$this->rooms = json_decode( file_get_contents( $this->roomsFile ) );
		}

		/**
		 * Log in Check And Return UserID
		 * @param  string $em email
		 * @param  string $pw password
		 * @return int        true|false
		 */
		public function login($em, $pw)
		{
			if ( $this->userExists($em) )
			{
				$uid = $this->getUserId($em);
				if ($this->members[ $uid - 1 ]->password == $pw)
				{
					return $uid;
				}
				else
				{
					$this->errors['login'] = $this->errorCodes[0];
					return false;
				}
			}
			else
			{
				$this->errors['login'] = $this->errorCodes[0];
				return false;
			}
		}

		/**
		 * Register user into members array variable and save users current state
		 * @param  string $nm fullname
		 * @param  string $em email
		 * @param  string $pw password
		 * @return boolean    true|false
		 */
		public function register($nm, $em, $pw)
		{
			if ( ! $this->userExists($em) )
			{
				$this->members[] = array(
					'fullname' => $nm,
					'email'    => $em,
					'password' => $pw
				);

				if ( $this->saveUsers() )
				{
					return true;
				}
				else
				{
					$this->errors['register'] = $this->errorCodes[1];
					return false;
				}
			}
			else
			{
				$this->errors['register'] = $this->errorCodes[2];
				return false;
			}
		}

		/**
		 * Create rooom into rooms array variable and save rooms current state
		 * @param  string $nm Room Name
		 * @param  string $pw Password
		 * @return boolean    true|false
		 */
		public function createRoom($nm, $pw)
		{
			if ( ! $this->roomExists($nm) )
			{
				$dumpFile = __DIR__.DIRECTORY_SEPARATOR.
				          $this->dumpFolder.DIRECTORY_SEPARATOR.
				          $this->encrypt($nm).'.json';

				$this->rooms[] = array(
					'name' => $nm,
					'password' => $pw,
				);

				touch($dumpFile);

				$o = fopen($dumpFile, 'w');
				$w = fwrite($o, '[]');
				fclose($o);

				if ( $this->saveRooms() )
				{
					return true;
				}
				else
				{
					$this->errors['createRoom'] = $this->errorCodes[1];
					return false;
				}
			}
			else
			{
				$this->errors['createRoom'] = $this->errorCodes[3];
				return false;
			}
		}

		/**
		 * Enter a exsisting rooms
		 * @param  integer $nm Room ID
		 * @param  string  $pw Password
		 * @return boolean    true|false
		 */
		public function enterRoom($rid, $pw)
		{
			if ( $this->rooms[ $rid ] )
			{
				if ( $this->rooms[$rid]->password == $pw )
				{
					return true;
				}
				else
				{
					$this->errors['enterRoom'] = $this->errorCodes[4];
					return false;
				}
			}
			else
			{
				$this->errors['enterRoom'] = $this->errorCodes[5];
				return false;
			}
		}

		/**
		 * Send a message into existing room
		 * @param  string  $msg Message String
		 * @param  int     $uid User ID
		 * @param  int     $rid Room ID
		 * @return boolean true|false
		 */
		public function sendMessage($msg, $uid, $rid)
		{
			$roomDump = (
				__DIR__ . DIRECTORY_SEPARATOR .
				$this->dumpFolder . DIRECTORY_SEPARATOR .
				$this->encrypt( $this->rooms[ $rid ]->name ) . '.json');

			$msgRow  = array(
				'userid' => $uid,
				'roomid' => $rid,
				'text'   => $msg,
				'time'   => time(),
			);

			//If Dump Not Exists, Create One
			if ( ! file_exists($roomDump) ) touch($roomDump);

			$this->currentRoom   = json_decode( file_get_contents($roomDump) );
			$this->currentRoom[] = $msgRow;

			if ( $this->saveCurrentRoom($roomDump) )
			{
				return true;
			}
			else
			{
				$this->errors['sendMessage'] = $this->errorCodes[6];
				return false;
			}
		}

		/**
		 * Get All Messages From Requested Room
		 * @param  int    $rid        Room ID
		 * @param  string $pwd        Room Password
		 * @param  bool   $formatted  Formatted Content
		 * @return bool|string        true|false|roomState
		 */
		public function getMessages($rid, $pwd, $formatted = false )
		{
			$roomContent = $formatted ? null : array();

			if ( isset( $this->rooms[ $rid ] ) )
			{
				if ( $this->rooms[ $rid ]->password == $pwd )
				{
					$roomDump = (
						__DIR__ . DIRECTORY_SEPARATOR .
						$this->dumpFolder . DIRECTORY_SEPARATOR .
						$this->encrypt($this->rooms[ $rid ]->name) . '.json'
					);

					if ( file_exists( $roomDump ) )
						$this->currentRoom = json_decode( file_get_contents($roomDump) );
					else
						touch($roomDump);

					if ( ! $formatted )
					{
						return $this->currentRoom;
					}
					else
					{
						if ( count($this->currentRoom) > 0 )
						{
							foreach ($this->currentRoom as $value)
							{
								$roomContent[] = array(
									'username' => $this->getUser( $value->userid )->fullname,
									'date'     => $this->formatTime($value->time),
									'text'     => $value->text,
									'avatar'   => '',
								);
							}
							return $roomContent;
						}
						else
						{
							$this->errors['getMessages'] = $this->errorCodes[11];
							return false;
						}
					}
				}
				else
				{
					$this->errors['getMessages'] = $this->errorCodes[4];
					return false;
				}
			}
			else
			{
				$this->errors['getMessages'] = $this->errorCodes[5];
				return false;
			}
		}

		/**
		 * Return All Rooms
		 * @return array Rooms
		 */
		public function getRooms()
		{
			$ret = array();
			foreach ($this->rooms as $value)
			{
				$ret[] = array(
					'name' => $value->name,
					'password' => $value->password,
					'messages' => $this->messagesCount($value->name),
				);
			}

			return $ret;
		}

		public function messagesCount($nm)
		{
			$dmp = json_decode(
				file_get_contents(
					__DIR__.DIRECTORY_SEPARATOR.
					$this->dumpFolder.DIRECTORY_SEPARATOR.
					$this->encrypt($nm).'.json'
				)
			);
			return count ( $dmp );
		}

		public function check($key)
		{
			if ( isset($_SESSION[ $key ]) )
				return $_SESSION[ $key ];
			else
				return $this->errorCodes[8];
		}

		public function getUser($uid)
		{
			if ( isset( $this->members[ $uid ] ) )
			{
				return $this->members[ $uid ];
			}
			else
			{
				$this->errors['getUser'] = $this->errorCodes[10];
				return false;
			}
		}

		public function formatTime($t)
		{
			return $t;
		}

		/**
		 * Save Members Array Variable's Current State To Members File
		 * @return boolean true|false
		 */
		private function saveUsers()
		{
			$dump = json_encode( $this->members );

			unlink( $this->membersFile );
			touch ( $this->membersFile );

			$o = fopen($this->membersFile, 'w');
			$w = fwrite($o, $dump);

			if ( $w )
				return true;
			else
				return false;
		}

		/**
		 * Save Rooms Array Variable's Current State To Rooms File
		 * @return boolean true|false
		 */
		private function saveRooms()
		{
			$dump = json_encode( $this->rooms );

			unlink( $this->roomsFile );
			touch ( $this->roomsFile );

			$o = fopen($this->roomsFile, 'w');
			$w = fwrite($o, $dump);

			if ( $w )
				return true;
			else
				return false;
		}

		/**
		 * If requested user exists return true, if don't return false
		 * @param  string $em E-Mail
		 * @return boolean    true|false
		 */
		private function userExists($em)
		{
			foreach ($this->members as $member)
			{
				if ( $em == $member->email )
					return true;
			}
		}

		/**
		 * If requested room exists return true, if don't return false
		 * @param  string  $nm  Room Name
		 * @param  boolean $rtI
		 * @return int|bool     true|int
		 */
		private function roomExists($nm, $rtI = false)
		{
			$cnt = 0;
			foreach ($this->rooms as $room)
			{
				$cnt++;
				if ( $nm == $room->name AND ! $rtI )
					return true;
				elseif ( $nm == $room->name AND $rtI )
					return $cnt;
			}
		}

		/**
		 * Get Requested user's ID
		 * @param  string $em E-Mail
		 * @return int        User ID
		 */
		private function getUserId($em)
		{
			$cnt = 0;
			foreach ($this->members as $member)
			{
				$cnt++;
				if ($member->email == $em)
				{
					return $cnt;
				}
			}
		}

		/**
		 * Save Current Room State To File
		 * @param  string $f File Name
		 * @return bool      true|false
		 */
		private function saveCurrentRoom($f)
		{
			$dump = json_encode( $this->currentRoom );

			unlink( $f ); touch ( $f );

			$o = fopen($f, 'w');
			$w = fwrite($o, $dump);

			if ( $w )
				return true;
			else
				return false;
		}

		/**
		 * Return requested index into $_GET global variable
		 * @param  string $i  Key Name
		 * @return string|int $_GET[ $i ]
		 */
		public function get($i)
		{
			return isset($_GET[ $i ]) ?
			htmlspecialchars(
				strip_tags(
					stripslashes(
						trim(
							$_GET[ $i ]
						)
					)
				)
			)
			: $this->errorCodes[8];
		}

		/**
		 * Return requested index into $_POST global variable
		 * @param  string $i Key Name
		 * @return type      $_POST[ $i ]
		 */
		public function post($i)
		{
			return isset($_POST[ $i ]) ?
			htmlspecialchars(
				strip_tags(
					stripslashes(
						trim(
							$_POST[ $i ]
						)
					)
				)
			)
			: $this->errorsodes[8];
		}

		/**
		 * Multi Encrypt Method For Passwords
		 * @param  string $str text or password what else
		 * @return string      encrypted text or password what else
		 */
		public function encrypt($str)
		{
			return md5( sha1( md5( sha1( $str ) ) ) );
		}

		/**
		 * Return errors array variable with requested index
		 * @param  boolean $i Key Name
		 * @return string     Error Code String
		 */
		public function getErrors($i = false)
		{
			if ( $i  )
				return $this->errors[ $i ];
			else
				return $this->errors;
		}

	}
