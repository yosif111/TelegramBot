<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram;
use App\Message;
use App\User;

class TelegramBot extends Controller
{
    public function getUnhandledMessages(){
        
        $latestOffset = Message::orderBy('created_at', 'desc')->first()->update_id;
        $response = Telegram::getUpdates(['offset' => $latestOffset ]);
        
        foreach($response as $message){
            if(!isset($message['message']['text'])) // not a text message, could be a picture or a video, pass it.
                continue;
            // create the user
            $user_id = $message['message']['from']['id'];
            $first_name = $message['message']['from']['first_name'];
            
            if(isset($message['message']['from']['last_name']))
                $last_name = $message['message']['from']['last_name'];
            else
                $last_name = null;
            
            if(isset($message['message']['from']['username']))
                $username = $message['message']['from']['username'];
            else
                $username = null;
            
            $fields = [ 'user_id' => $user_id, 'first_name' => $first_name,
            'username' => $username ];
            
            $user =  User::updateOrCreate($fields);
            
            //create the message
            $user_id = $message['message']['from']['id'];
            $update_id = $message['update_id'];
            $message_id = $message['message']['message_id'];
            $chat_id =  $message['message']['chat']['id'];
            
            $text = $message['message']['text'];
            
            $fields = [ 'update_id' => $update_id, 'user_id' => $user_id,
            'message_id' => $message_id,'chat_id' => $chat_id,
            'text' => $text ]; // handled will default to 0
            
            $message = Message::firstOrCreate($fields);
        }

        $data =  $this->classifyUnhandledMessages();
        $this->handleMessages($data);

        return $data;
    }
    
    
    protected function classifyUnhandledMessages(){
        $ml = new \MonkeyLearn\Client(env('MONKEYLEARN_SECRET'));
        
        $data = Message::where('handled', 0)->get();
        
        $text = $data->map(function ($message) {
            return $message['text'];
        });
        
        if(count($data) == 0 ) // not to consume the api limit
            return
        
        $model_id = 'cl_pi3C7JiL';
        $sentiments = $ml->classifiers->classify($model_id, $text->toArray(), true);
        
        $result = $data->each(function (&$message, $index) use ($sentiments)  {
            $message['classification'] = $sentiments->result[$index]['classifications'][0]['tag_name'];
        });
        
        return $result;
    }
    
    protected function handleMessages($messages){
        
        foreach($messages as $message){
            
            Telegram::sendMessage([
            'chat_id' => $message['chat_id'],
            'text' => $message['classification'],
            'reply_to_message_id' => $message['message_id']
            ]);
            //update database
            $record = Message::find($message['update_id']);
            $record->handled = 1;
            $record->save();
        }
    }
    
    public function sendMessage(Request $request){
        
        $response = Telegram::sendMessage([
        'chat_id' => $request['chat_id'],
        'text' => $request['text'],
        'reply_to_message_id' => $request['reply_to_message_id']
        ]);
        
        return $response;
    }
}