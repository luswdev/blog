<?php
define('BOT_TOKEN', '868385679:AAHea69gcXkC19t85sCx7BUbgFhWWCqpdQc');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

$request = file_get_contents("php://input");
$content = json_decode($request, true);

if( !$request )
{
    echo "[".date("Y-n-d H:i:s")."] input error";
}
else if( !isset($content['update_id']) || !isset($content['message']) )
{
    echo "[".date("Y-n-d H:i:s")."] message empty";
}
else {
    $chatID = $content["message"]["chat"]["id"];
    $got_message = $content["message"]["text"];
    
    switch ($got_message){
        case "/start":
            $reply = "I am poop, and so do you.";
            break;
        case "/help":
            $reply = "I am poop, and so do you.";
            break;
        case "/game":
            $reply = "Do you wanna play a game?";
            break;
        case "/info":
            $replys = "Which kind data do you want to see👀?";
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Success info', 'callback_data' => 'success'],
                        ['text' => 'Failed info', 'callback_data' => 'failed']
                    ]
                ]
        
            ];
            $encodedKeyboard = json_encode($keyboard);
            $parameters = 
                array(
                    'chat_id' => $chatID, 
                    'text' => $replys, 
                    'reply_markup' => $encodedKeyboard
                );
            send('sendMessage', $parameters);
            break;
        default:
            $reply = $got_message;
            break;
    }

    $sendto =API_URL."sendmessage?chat_id=".$chatID."&text=".$reply;
    file_get_contents($sendto);
}

if ($content["callback_query"]){

	include_once('_partial/db.php');
    
    switch ($content["callback_query"]['data']){
        case "success":
            $sql = "SELECT * FROM `login_log` ORDER BY `login_id` desc";
            $stmt = $mysqli->prepare($sql);
            $stmt->execute();
            $stmt->bind_result($loginid,$acco,$loginT,$logoutT,$logIP);
            $call_back = '💀*Success List*💀'.'%0A'.'%0A';
            for ($i=0; $i<10; $i++){
                $stmt->fetch();
                $call_back .= "`".$loginid.'`%0A'.
                              "*User:* _".$acco."_".'%0A'.
                              "*Sign in:* ".$loginT."  ".'%0A'.
                              "*Sign out:* ".$logoutT."  ".'%0A'.
                              "*IP:* `".$logIP."`".'%0A'.'%0A';
            }
            $stmt->close();
            break;
        case "failed":
            $sql = "SELECT * FROM `login_error_log` ORDER BY `id` desc";
            $stmt = $mysqli->prepare($sql);
            $stmt->execute();
            $stmt->bind_result($loginid,$acco,$tryT,$logIP);
            $call_back = '😈`Failed List`😈'.'%0A'.'%0A';
            for ($i=0; $i<10; $i++){
                $stmt->fetch();
                $call_back .= "`".$loginid.'`%0A'.
                              "*User:* _".$acco."_".'%0A'.
                              "*Time:* ".$tryT.'%0A'.
                              "*IP:* `".$logIP."`".'%0A'.'%0A';
            }
            $stmt->close();
            break;
        default:
            $call_back = 'WTF do you press?';
            break;
    }

    $sendto =API_URL."sendmessage?chat_id=460873343&text=".$call_back."&parse_mode=markdown";
    file_get_contents($sendto);
}

function send($method, $data)
{
    $url = API_URL. $method;

    if (!$curld = curl_init()) {
        exit;
    }
    curl_setopt($curld, CURLOPT_POST, true);
    curl_setopt($curld, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curld, CURLOPT_URL, $url);
    curl_setopt($curld, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($curld);
    curl_close($curld);
    return $output;
}

?>
