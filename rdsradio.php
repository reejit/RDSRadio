#!/usr/bin/env php
<?php

set_include_path(get_include_path().':'.realpath(dirname(__FILE__).'/MadelineProto/'));

if (!file_exists(__DIR__.'/vendor/autoload.php')) {
    echo 'You did not run composer update, using madeline.php'.PHP_EOL;
    if (!file_exists('madeline.php')) {
        copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
    }
    include 'madeline.php';
} else {
    require_once 'vendor/autoload.php';
}
if (file_exists('web_data.php')) {
    require_once 'web_data.php';
}

echo 'Deserializing MadelineProto from session.madeline...'.PHP_EOL;

$MadelineProto = new \danog\MadelineProto\API('session.madeline', ['secret_chats' => ['accept_chats' => false]]);
$MadelineProto->start();

if (!isset($MadelineProto->programmed_call)) {
    $MadelineProto->programmed_call = [];
}
$MadelineProto->session = 'session.madeline';

foreach (['my_users', 'times', 'times_messages', 'calls'] as $key) {
    if (!isset($MadelineProto->{$key})) {
        $MadelineProto->{$key} = [];
    }
}

class EventHandler extends \danog\MadelineProto\EventHandler
{

    public function configureCall($call)
    {
      $icsd = date("U");

      shell_exec("mkdir streams");

    file_put_contents("omg.sh", "#!/bin/bash
mkfifo streams/$icsd.raw");

file_put_contents("figo.sh", "#!/bin/bash
ffmpeg -i http://stream1.rds.it:8000/apprds128 -f s16le -ac 1 -ar 48000 -acodec pcm_s16le pipe:1 > streams/$icsd.raw");

shell_exec("sudo chmod -R 755 /home/gabboxl/");

      shell_exec("./omg.sh");

      shell_exec("screen -S sleepy$icsd -dm ./figo.sh");

        $call->configuration['enable_NS'] = false;
        $call->configuration['enable_AGC'] = false;
        $call->configuration['enable_AEC'] = false;
        $call->configuration['shared_config'] = [
            'audio_init_bitrate'      => 100 * 1000,
            'audio_max_bitrate'       => 100 * 1000,
            'audio_min_bitrate'       => 10 * 1000,
            'audio_congestion_window' => 4 * 1024,
            //'audio_bitrate_step_decr' => 0,
            //'audio_bitrate_step_incr' => 2000,
        ];
        $call->parseConfig();
        $call->playOnHold(["streams/$icsd.raw"]);



      }


    public function handleMessage($chat_id, $from_id, $message)
    {
        try {



            if (!isset($this->my_users[$from_id]) || $message === '/start') {
                $this->my_users[$from_id] = true;
                $this->messages->sendMessage(['no_webpage' => true, 'peer' => $chat_id, 'message' => "Ciao! Sono la prima RDS webradio su Telegram! <b>Chiamami</b> oppure scrivimi <b>/call</b>!

                Creato con amore da @Gabbo_xl usando @madelineproto.", 'parse_mode' => 'html']);
            }
            if (!isset($this->calls[$from_id]) && $message === '/call') {
                $call = $this->request_call($from_id);
                $this->configureCall($call);
                $this->calls[$call->getOtherID()] = $call;


                      /*NOW PLAYING (old)
                               $url = 'http://stream1.rds.it:8000/status.xsl';

                               $dom = new DOMDocument();
                               @$dom->loadHTML(file_get_contents("http://stream1.rds.it:8000/status.xsl"));

                               $xpath = new DOMXPath($dom);

                               $colorWaitingNumber = $xpath->query("/html/body/div[9]/div[2]/table/tbody/tr[10]/td[2]");
                               foreach( $colorWaitingNumber as $node )
                               {
                                 $this->times[$call->getOtherID()] = [time(), $this->messages->sendMessage(['peer' => $call->getOtherID(), 'message' => "Stai ascoltando: $node->nodeValue", 'parse_mode' => 'Markdown'])['id']];
                               }
                               */


                               //count calls running now
              //    $this->messages->sendMessage(['no_webpage' => true, 'peer' => $chat_id, 'message' => 'Al momento ci sono '.count($this->calls).' chiamate in corso!', 'parse_mode' => 'Markdown']);


                        //NOW PLAYING +
                         $url = 'http://stream1.rds.it:8000/status-json.xsl';
                         $jsonroba = file_get_contents($url);
                         $jsonclear = json_decode($jsonroba, true);
                         $robas = explode("*", $jsonclear["icestats"]["source"][4]["title"]);

                         $this->times[$call->getOtherID()] = [time(), $this->messages->sendMessage(['peer' => $call->getOtherID(), 'message' => "Stai ascoltando: ".$robas, 'parse_mode' => 'Markdown'])['id']];

              }


        /*    if (strpos($message, '/program') === 0) {
                $time = strtotime(str_replace('/program ', '', $message));
                if ($time === false) {
                    $this->messages->sendMessage(['peer' => $chat_id, 'message' => 'Invalid time provided']);
                } else {
                    $this->programmed_call[] = [$from_id, $time];
                    $this->messages->sendMessage(['peer' => $chat_id, 'message' => 'OK']);
                }
            }
            if ($message === '/broadcast' && $from_id === 218297024) {
                $time = time() + 100;
                $message = explode(' ', $message, 2);
                unset($message[0]);
                $message = implode(' ', $message);
                foreach ($this->get_dialogs() as $peer) {
                    $this->times_messages[] = [$peer, $time, $message];
                    if (isset($peer['user_id'])) {
                        $this->programmed_call[] = [$peer['user_id'], $time];
                    }
                    $time += 30;
                }
            } */

        } catch (\danog\MadelineProto\RPCErrorException $e) {
            try {
                if ($e->rpc === 'USER_PRIVACY_RESTRICTED') {
                    $e = 'Disattiva la privacy delle chiamate nelle impostazioni!';
                } elseif (strpos($e->rpc, 'FLOOD_WAIT_') === 0) {
                    $t = str_replace('FLOOD_WAIT_', '', $e->rpc);
                    $this->programmed_call[] = [$from_id, time() + 1 + $t];
                    $e = "Too many people used the /call function. I'll call you back in $t seconds.\nYou can also call me right now.";
                }
                $this->messages->sendMessage(['peer' => $chat_id, 'message' => (string) $e]);
            } catch (\danog\MadelineProto\RPCErrorException $e) {
            }
            echo $e;
        } catch (\danog\MadelineProto\Exception $e) {
            echo $e;
        }
    }

    public function onUpdateNewMessage($update)
    {
        if ($update['message']['out'] || $update['message']['to_id']['_'] !== 'peerUser' || !isset($update['message']['from_id'])) {
            return;
        }
        $chat_id = $from_id = $this->get_info($update)['bot_api_id'];
        $message = isset($update['message']['message']) ? $update['message']['message'] : '';
        $this->handleMessage($chat_id, $from_id, $message);
    }

    public function onUpdateNewEncryptedMessage($update)
    {
        return;
        $chat_id = $this->get_info($update)['InputEncryptedChat'];
        $from_id = $this->get_secret_chat($chat_id)['user_id'];
        $message = isset($update['message']['decrypted_message']['message']) ? $update['message']['decrypted_message']['message'] : '';
        $this->handleMessage($chat_id, $from_id, $message);
    }

    public function onUpdateEncryption($update)
    {
        return;

        try {
            if ($update['chat']['_'] !== 'encryptedChat') {
                return;
            }
            $chat_id = $this->get_info($update)['InputEncryptedChat'];
            $from_id = $this->get_secret_chat($chat_id)['user_id'];
            $message = '';
        } catch (\danog\MadelineProto\Exception $e) {
            return;
        }
        $this->handleMessage($chat_id, $from_id, $message);
    }

    public function onUpdatePhoneCall($update)
    {

        if (is_object($update['phone_call']) && isset($update['phone_call']->madeline) && $update['phone_call']->getCallState() === \danog\MadelineProto\VoIP::CALL_STATE_INCOMING) {
            $this->configureCall($update['phone_call']);
            if ($update['phone_call']->accept() === false) {
                echo 'DID NOT ACCEPT A CALL';
            }

            //MOOSECA

        //    $this->messages->sendMessage(['no_webpage' => true, 'peer' => $id_utente_chiamata, 'message' => 'Al momento ci sono '.count($this->calls).' chiamate in corso!', 'parse_mode' => 'Markdown']);




            $this->calls[$update['phone_call']->getOtherID()] = $update['phone_call'];


            try {

              /*NOW PLAYING (old)

              $url = 'http://stream1.rds.it:8000/status.xsl';


              $dom = new DOMDocument();
              @$dom->loadHTML(file_get_contents("http://stream1.rds.it:8000/status.xsl"));

              $xpath = new DOMXPath($dom);

              $colorWaitingNumber = $xpath->query("/html/body/div[9]/div[2]/table/tbody/tr[10]/td[2]");
              foreach( $colorWaitingNumber as $node )
              {
                $robas = explode("*", $node->nodeValue);
              //  $this->times[$update['phone_call']->getOtherID()] = [time(), $this->messages->sendMessage(['peer' => $update['phone_call']->getOtherID(), 'message' => "Stai ascoltando: <b>".$robas[0]."</b>  ".$robas[1], 'parse_mode' => 'html'])['id']];
              $this->times[$update['phone_call']->getOtherID()] = [time(), $this->messages->sendMessage(['peer' => $update['phone_call']->getOtherID(), 'message' => "Stai ascoltando: <b>".$robas[0]."</b>  ", 'parse_mode' => 'html'])['id']];
              }
              */

              //NOW PLAYING +
               $url = 'http://stream1.rds.it:8000/status-json.xsl';
               $jsonroba = file_get_contents($url);
               $jsonclear = json_decode($jsonroba, true);

               $robas = explode("*", $jsonclear["icestats"]["source"][4]["title"]);
               $this->times[$update['phone_call']->getOtherID()] = [time(), $this->messages->sendMessage(['peer' => $update['phone_call']->getOtherID(), 'message' => "Stai ascoltando: <b>".$robas[0]."</b>  ", 'parse_mode' => 'html'])['id']];




            } catch (\danog\MadelineProto\RPCErrorException $e) {
            }
        }

/*

        if (is_object($update['phone_call']) and isset($update['phone_call']->madeline) and $update['phone_call']->getCallState() > \danog\MadelineProto\VoIP::CALL_STATE_READY) {
            try {

              $id_utente_chiamata = $update['phone_call']->getOtherID();
              $emojis = $update['phone_call']->getVisualization();
              $this->messages->sendMessage(['no_webpage' => true, 'peer' => $id_utente_chiamata, 'message' => 'Emojis: '.$emojis[0].$emojis[1].$emojis[2].$emojis[3] , 'parse_mode' => 'Markdown']);


            } catch (\danog\MadelineProto\RPCErrorException $e) {
            }
        }

*/


    }

    public function onLoop()
    {
        foreach ($this->programmed_call as $key => $pair) {
            list($user, $time) = $pair;
            if ($time < time()) {
                if (!isset($this->calls[$user])) {
                    try {
                        $call = $this->request_call($user);
                        $this->configureCall($call);
                        $this->calls[$call->getOtherID()] = $call;
                      //  $this->times[$call->getOtherID()] = [time(), $this->messages->sendMessage(['peer' => $call->getOtherID(), 'message' => 'Total running calls: '.count($this->calls).PHP_EOL.PHP_EOL.$call->getDebugString()])['id']];
                      $this->times[$call->getOtherID()] = [time(), $this->messages->sendMessage(['peer' => $call->getOtherID(), 'message' => 'Chiamate in corso: '.count($this->calls)])['id']];
                    } catch (\danog\MadelineProto\RPCErrorException $e) {
                        try {
                            if ($e->rpc === 'USER_PRIVACY_RESTRICTED') {
                                $e = 'Disattiva la privacy delle chiamate nelle impostazioni!';
                            } elseif (strpos($e->rpc, 'FLOOD_WAIT_') === 0) {
                                $t = str_replace('FLOOD_WAIT_', '', $e->rpc);
                                $this->programmed_call[] = [$user, time() + 1 + $t];
                                $e = "Ti potrò chiamare tra $t secondi.\nSe vuoi puoi anche chiamarmi direttamente senza aspettare.";
                            }
                            $this->messages->sendMessage(['peer' => $user, 'message' => (string) $e]);
                        } catch (\danog\MadelineProto\RPCErrorException $e) {
                        }
                    }
                }
                unset($this->programmed_call[$key]);
            }
            break;
        } //fine foreach per chiamate programmate


        foreach ($this->times_messages as $key => $pair) {
            list($peer, $time, $message) = $pair;
            if ($time < time()) {
                try {
                    $this->messages->sendMessage(['peer' => $peer, 'message' => $message]);
                } catch (\danog\MadelineProto\RPCErrorException $e) {
                    if (strpos($e->rpc, 'FLOOD_WAIT_') === 0) {
                        $t = str_replace('FLOOD_WAIT_', '', $e->rpc);
                        $this->times_messages[] = [$peer, time() + 1 + $t, $message];
                    }
                    echo $e;
                }
                unset($this->times_messages[$key]);
            }
            break;
        }

          //roba nome kon kose in askolto++++
                  if(1 == 1)
                  {
                            try{
                              /* old
                              $dom1 = new DOMDocument();
                              @$dom1->loadHTML(file_get_contents("http://stream1.rds.it:8000/status.xsl"));

                              $xpath1 = new DOMXPath($dom1);

                              $roba1 = $xpath1->query("/html/body/div[9]/div[2]/table/tbody/tr[10]/td[2]");


                              foreach( $roba1 as $node )
                              {
                                $texto = file_get_contents("testmoseca.php");
                                if($texto != $node->nodeValue)
                                {
                                  $robas = explode("*", $node->nodeValue);
                                  $this->account->updateProfile(['last_name' => "/ Playing: ".$robas[0]."-".$robas[1]]);
                                  file_put_contents("testmoseca.php", $node->nodeValue);
                                }
                              }
                              */

                              $url = 'http://stream1.rds.it:8000/status-json.xsl';
                              $jsonroba = file_get_contents($url);
                              $jsonclear = json_decode($jsonroba, true);
                              $robas = explode("*", $jsonclear["icestats"]["source"][4]["title"]);
                              $this->account->updateProfile(['last_name' => "/ Playing: ".$robas[0]."-".$robas[1]]);

                              file_put_contents("testmoseca.php", $jsonclear);

                            } catch (\danog\MadelineProto\RPCErrorException | \danog\MadelineProto\Exception $e) {
                                echo $e;
                            }
                    }



        \danog\MadelineProto\Logger::log(count($this->calls).' calls running!');
        foreach ($this->calls as $key => $call) {

          if ($call) {
              try {/*
                $dom = new DOMDocument();
                              @$dom->loadHTML(file_get_contents("http://stream1.rds.it:8000/status.xsl"));

                              $xpath = new DOMXPath($dom);

                              $roba = $xpath->query("/html/body/div[9]/div[2]/table/tbody/tr[10]/td[2]");
                              foreach( $roba as $node )
                              {

                              $robas = explode("*", $node->nodeValue);
                                $this->messages->editMessage(['id' => $this->times[$call->getOtherID()][1], 'peer' => $call->getOtherID(), 'message' => "Stai ascoltando: <b>".$robas[0]."</b>  ".$robas[1], 'parse_mode' => 'Markdown' ]);
                              }
                              */

                              $url = 'http://stream1.rds.it:8000/status-json.xsl';
                              $jsonroba = file_get_contents($url);
                              $jsonclear = json_decode($jsonroba, true);
                              $robas = explode("*", $jsonclear["icestats"]["source"][4]["title"]);
                              $this->messages->editMessage(['id' => $this->times[$call->getOtherID()][1], 'peer' => $call->getOtherID(), 'message' => "Stai ascoltando: <b>".$robas[0]."</b>  ".$robas[1], 'parse_mode' => 'Markdown' ]);

              } catch (\danog\MadelineProto\RPCErrorException | \danog\MadelineProto\Exception $e) {
                  echo $e;
              }
          }



            if ($call->getCallState() === \danog\MadelineProto\VoIP::CALL_STATE_ENDED) {
                unset($this->calls[$key]);
            } elseif (isset($this->times[$call->getOtherID()]) && $this->times[$call->getOtherID()][0] < time()) {
                $this->times[$call->getOtherID()][0] += 30 + count($this->calls);



                try {
                } catch (\danog\MadelineProto\RPCErrorException $e) {
                    echo $e;
                }
            }
        }

    }
}

$MadelineProto->setEventHandler('\EventHandler');
$MadelineProto->loop();
