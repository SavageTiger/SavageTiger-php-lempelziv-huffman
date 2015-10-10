<?php

class TokenEncoder
{
    /**
     * Create a token
     *
     * @param integer $offset   12 bit offset (0..4095)
     * @param integer $length   4 bit length (0..15)
     *
     * @return string
     */
    public function encodeToken($offset, $length)
    {
        // More then 15 will not fit in a 4 bits
        if ($length > 15) {
            return false;
        }

        // More then 4095 will not fit in a 12 bits
        if ($offset > 4095) {
            return false;
        }

        $tokenBytes =
            str_pad(decbin($offset), 12, '0', STR_PAD_LEFT) .
            str_pad(decbin($length), 4, '0', STR_PAD_LEFT);

        $baseConvert = str_pad(base_convert($tokenBytes, 2, 16), 2, '0', STR_PAD_LEFT);

        if (strlen($baseConvert) == 3) {
            $baseConvert = str_pad($baseConvert, 4, '0', STR_PAD_LEFT);
        }

        $tokenBytes  = pack('H*', $baseConvert);


        if (strlen($tokenBytes) === 1) {
            return "\0" . $tokenBytes;
        }

        return $tokenBytes;
    }

    /**
     * Decode the token
     *
     * @param string $bytes The token (2 bytes)
     *
     * @return array|string
     */
    public function decodeToken($bytes)
    {
        $hex    = bin2hex($bytes);
        $binary = base_convert($hex, 16, 2);

        // Make sure the binary is padded with leading zeros
        $tokenBytes = str_pad(
            $binary, 16, '0', STR_PAD_LEFT
        );

        $dataOffset = bindec(substr($tokenBytes, 0, 12));
        $dataLength = bindec(substr($tokenBytes, 12, 4));

        return [$dataOffset, $dataLength];
    }

    /**
     * @param string    $bytes    The token (2 bytes)
     * @param string    $stream   Datastream to retrieve the data from (previous buffer block)
     *
     * @return string
     */
    public function fetchTokenDataFromStream($bytes, $stream)
    {
        $tokenData = $this->decodeToken($bytes);

        return substr($stream, $tokenData[0], $tokenData[1]);
    }
}