<?php

require('TokenEncoder.php');

class LempelZiv
{
    const TOKEN_PREFIX = '~';

    const PACKET_SIZE = 4096;

    /**
     * @var TokenEncoder
     */
    protected $tokenEncoder;

    protected $buffer = array();

    protected $tokens = array();

    function __construct($input)
    {
        $this->tokenEncoder = new TokenEncoder();

        $this->buffer = str_split($input, $this::PACKET_SIZE);
    }

    public function compress()
    {
        $this->tokanize();

        return $this->writeTokens();
    }

    public function uncompress()
    {
        return $this->readTokens();
    }

    protected function readTokens()
    {
        $origin       = implode('', $this->buffer);
        $originLength = strlen($origin);

        $buffer = '';

        if (isset($this->buffer[0])) {
            $buffer = $this->buffer[0];
        }

        for ($i = strlen($buffer); $i < $originLength; $i++) {
            $char = $origin[$i];

            if ($char === $this::TOKEN_PREFIX) {
                $tokenBytes = $origin[$i + 1] . $origin[$i + 2];

                if ($tokenBytes === $this::TOKEN_PREFIX . $this::TOKEN_PREFIX) {
                    $buffer .= $this::TOKEN_PREFIX;
                } else {
                    $data = $this->tokenEncoder->fetchTokenDataFromStream($tokenBytes, $buffer);

                    $buffer .= $data;
                }

                $i += 2;
            } else {
                $buffer .= $origin[$i];
            }
        }

        return $buffer;
    }

    protected function writeTokens()
    {
        $origin       = implode('', $this->buffer);
        $originLength = strlen($origin);
        $writeOffset  = 0;
        $buffer       = '';

        if (isset($this->buffer[0])) {
            $buffer = $this->buffer[0];

            $writeOffset += strlen($buffer);
        }

        foreach ($this->tokens as $token) {
            $tokenOffsets[$token[2]] = $token;
        }

        while ($writeOffset < $originLength) {
            if (isset($tokenOffsets[$writeOffset])) {
                $token      = $tokenOffsets[$writeOffset];
                $tokenBytes = $this->tokenEncoder->encodeToken($token[0], $token[1]);

                $buffer .= $this::TOKEN_PREFIX . $tokenBytes;

                unset($tokenOffsets[$writeOffset]);

                $writeOffset += $token[1];
            } else {
                $buffer .= $origin[$writeOffset];

                // Escape the token identifier character
                // (tested using "~", there is no token that contains "~~" as binary representation)
                if ($origin[$writeOffset] === $this::TOKEN_PREFIX) {
                    $buffer .= $this::TOKEN_PREFIX . $this::TOKEN_PREFIX;
                }

                $writeOffset++;
            }
        }

        return $buffer;
    }

    protected function tokanize()
    {
        foreach ($this->buffer as $offset => $lookAhead) {
            if ($offset > 0) {
                $searchLength = strlen($lookAhead);
                $searchBuffer = $this->buffer[$offset - 1];
                $searchOffset = 0;

                while ($searchOffset < $searchLength) {
                    if ($token = $this->findToken($lookAhead, $searchBuffer, $searchOffset)) {
                        $token[] = (($this::PACKET_SIZE * $offset) + $searchOffset);

                        $this->tokens[] = $token;

                        $searchOffset += $token[1];
                    }

                    $searchOffset++;
                }
            }
        }
    }

    protected function findToken(&$lookAhead, &$searchBuffer, $offset)
    {
        $token        = null;
        $occurance    = null;

        $searchLength = strlen($searchBuffer);
        $length       = 0;

        while ($occurance !== false && $length < 15) {
            if ($length > $searchLength) {
                return false;
            }

            $length++;

            $needle    = substr($lookAhead, $offset, $length);
            $occurance = strpos($searchBuffer, $needle, $offset);

            if ($occurance !== false) {
                $token = $occurance;
            } else {
                $length--;

                break;
            }
        }

        if ($length > 3 && $length < 15) {
            $jump = $offset + ($this::PACKET_SIZE - $token);

            if ($jump > 4095) {
                return false;
            }

            return [$jump, $length];
        }

        return false;
    }
}


