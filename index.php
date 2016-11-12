<?php 

	define('SEC', TRUE);
	session_start();
	
	require_once __DIR__ . '/ByteChat.php';

	$byteChat = new ByteChat();
	
	if ( ! $byteChat->requested )
	{
		echo $byteChat->getErrors('bootstrap');
		die();
	}

	switch ($byteChat->requested)
	{
		case 'login':
			$email = $byteChat->get('email');
			$passw = $byteChat->encrypt( $byteChat->get('password') );

			$login = $byteChat->login($email, $passw);
			if ( $login )
			{
				$_SESSION['userid'] = $login - 1;
				echo $login - 1;
			}
			else
			{
				echo $byteChat->getErrors('login');
			}
		break;
		
		case 'register':
			$fname = $byteChat->get('fullname');
			$email = $byteChat->get('email');
			$passw = $byteChat->encrypt( $byteChat->get('password') );

			$register = $byteChat->register($fname, $email, $passw);

			if ( $register )
				echo 'SUCCESS';
			else
				echo $byteChat->getErrors('register');
		break;

		case 'create':
			$name = $byteChat->post('name');
			$pass = $byteChat->post('password', true) ?
					$byteChat->encrypt( $byteChat->post('password') ) : $byteChat->encrypt('');

			$create = $byteChat->createRoom($name, $pass);

			if ( $create )
				echo 'SUCCESS';
			else
				echo $byteChat->getErrors('createRoom');
		break;

		case 'enter':
			$room = $byteChat->get('roomid');
			$pass = $byteChat->get('password', true) ?
					$byteChat->encrypt( $byteChat->get('password') ) : $byteChat->encrypt('');

			$enter = $byteChat->enterRoom($room, $pass);

			if ( $enter )
				echo 'SUCCESS';
			else
				echo $byteChat->getErrors('enterRoom');
		break;

		case 'put':
			$msg = $byteChat->post('message');
			$uid = $byteChat->post('userid');
			$rid = $byteChat->post('roomid');

			$send = $byteChat->sendMessage($msg, $uid, $rid);

			if ( $send )
				echo 'SUCCESS';
			else
				echo $byteChat->getErrors('sendMessage');
		break;

		case 'get':
			$rid = $byteChat->get('roomid');
			$pwd = $byteChat->encrypt( $byteChat->get('password') );

			$getMessages = $byteChat->getMessages($rid, $pwd, true);

			if ( $getMessages )
				echo json_encode($getMessages);
			else
				echo json_encode(
					array(
						'error' => $byteChat->getErrors('getMessages')
					)
				);
		break;

		case 'getrooms':
			$rooms = $byteChat->getRooms();
			echo json_encode($rooms);
		break;	

		case 'check':
			$key = $byteChat->get('key');
			echo $byteChat->check($key);
		break;

		case 'getuser':
			$uid = $byteChat->get('userid');

			$user = $byteChat->getUser( /*$uid*/ -1 );

			if ($user)
				echo json_encode($user);
			else
				echo json_encode(
					array(
						'error' => $byteChat->getErrors('getUser')
					)
				);
		break;

		default:
			die('Access Denied !');
		break;
	}

?>