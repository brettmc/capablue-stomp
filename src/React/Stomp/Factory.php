<?php

namespace React\Stomp;

use React\EventLoop\LoopInterface;
use React\Stomp\Exception\ConnectionException;
use React\Stomp\Io\InputStream;
use React\Stomp\Io\OutputStream;
use React\Stomp\Protocol\Parser;
use React\Socket\Connection;

class Factory
{
    /**
     * Protocol : defaults to 'tcp', for secure connection use 'tls'
     * @param $defaultOptions['protocol'] 'tcp' | 'tls' defines the protocol
     */
    private $defaultOptions = array(
        'host'      => '127.0.0.1',
        'port'      => 61613,
        'vhost'     => '/',
        'login'     => 'guest',
        'passcode'  => 'guest',
        'protocol'  => 'tcp',
        'timeout'   => 0
    );

    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function createClient(array $options = array())
    {
        $options = array_merge($this->defaultOptions, $options);

        $conn = $this->createConnection($options);

        $parser = new Parser();
        $input = new InputStream($parser);
        $conn->pipe($input);

        $output = new OutputStream($this->loop);
        $output->pipe($conn);

        $conn->on('error', function ($e) use ($input) {
            $input->emit('error', array($e));
        });

        $conn->on('end', function ($e) use ($input) {
            $input->emit('end', array($e));
        });

        return new Client($this->loop, $input, $output, $options);
    }

    public function createConnection($options)
    {
        $address = $options['protocol'] . '://'.$options['host'].':'.$options['port'];

        if (false === $fd = @stream_socket_client($address, $errno, $errstr)) {
            $message = "Could not bind to $address: $errstr";
            throw new ConnectionException($message, $errno);
        }

        // Normally all reads are non-blocking, and stream_socket_recvfrom ignores it at all
        // so it should have no effect.
        if ((int)$options['timeout'] && !stream_set_timeout($fd, (int)$options['timeout'] )) {
                throw new ConnectionException('Failed to set timeout ' . $options['timeout'] . 'seconds.');
        }

        $conn = new Connection($fd, $this->loop);

        return $conn;
    }
}
