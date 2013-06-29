<?php

set_time_limit (0);

/**
 * @author Kosta Korenkov <7r0ggy@gmail.com>
 */
class MockPOP3Server {

    private $clientConnected;

    private $listen_socket;

    public function __construct($port = 33110) {
        $this->listen_socket = socket_create_listen($port, 1);
        if (!$this->listen_socket) {
            throw new Exception("Cannot create listen socket on port $port");
        }
        while(true) {
            if(($client = socket_accept($this->listen_socket)) !== false) {
                $this->clientConnected = true;
                $this->greet($client);
                do {
                    $input = socket_read($client, 128);
                    list($command, $argument) = $this->parseInput($input);
                    $this->process($client, $command, $argument);
                } while ($this->clientConnected);
            }
        }
    }

    public function __destruct() {
        if ($this->listen_socket) {
            socket_close($this->listen_socket);
        }
    }

    function greet($client) {
        $this->respond($client, "+OK POP3 server ready\r\n");
    }

    function getMessages() {
        $message = file_get_contents(dirname(__FILE__)."/fixture.eml", 'r');
        return array($message);
    }

    function respond($client, $text) {
        $this->log("$client: > $text");
        socket_write($client, $text);
    }

    function log($msg) {
        echo $msg;
    }

    function parseInput($input) {
        $command = "";
        $argument = "";

        if(preg_match('/(.*?)(?:\s+(.*?))?[\r\n]+/', $input, $matches)) {
            $command = $matches[1];
            $argument = $matches[2];
        }
        return array($command, $argument);
    }

    function stat($client) {
        $message_count = count($this->getMessages());
        $mailbox_size = 0;
        foreach($this->getMessages() as $message) {
            $mailbox_size += strlen($message);
        }
        $this->respond($client, "+OK $message_count $mailbox_size\r\n");
    }

    function _list($client, $argument) {
        $start_index = (int) $argument;
        $messages = $this->getMessages();
        $message_count = count($messages) - $start_index;
        $total_size = 0;
        for($i = $start_index; $i < count($messages); $i++) {
            $total_size += strlen($messages[$i]);
        }
        $write_buffer = "+OK $message_count messages ($total_size octets)\r\n";
        for($i = $start_index; $i < count($messages); $i++) {
            $message_id = $i + 1;
            $message_size = strlen($messages[$i]);
            $write_buffer .= "$message_id $message_size\r\n";
        }
        $write_buffer .= ".\r\n";
        $this->respond($client, $write_buffer);
    }

    function retr($client, $argument) {
        $message_id = (int) $argument;
        $messages = $this->getMessages();
        $message = $messages[$message_id - 1];
        $message_size = strlen($message);
        $this->respond($client, sprintf("+OK %d octets\r\n%s\r\n.\r\n", $message_size, $message));
    }


    function process($client, $command, $argument) {
        $this->log("$client: < $command $argument");
        switch($command) {
            case 'USER': $this->respond($client, "+OK $argument is welcome here\r\n"); break;
            case 'PASS': $this->respond($client, "+OK mailbox has " . count($this->getMessages()) . " message(s)\r\n"); break;
            case 'QUIT': $this->clientConnected = false; $this->respond($client, "+OK POP3 server signing off\r\n"); break;
            case 'STAT': $this->stat($client); break;
            case 'LIST': $this->_list($client, $argument); break;
            case 'RETR': $this->retr($client, $argument); break;
            case 'DELE': $this->respond($client, "+OK\r\n"); break;
            case 'NOOP': $this->respond($client, "+OK\r\n"); break;
            case 'LAST': $this->respond($client, "+OK " . count($this->getMessages()) . "\r\n"); break;
            case 'RSET': $this->respond($client, "+OK\r\n"); break;
            default:     $this->respond($client, "-ERR Unknown command '$command'\r\n"); break;
        }
    }

}

$server = new MockPOP3Server();
