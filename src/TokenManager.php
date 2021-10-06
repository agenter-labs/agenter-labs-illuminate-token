<?php

namespace AgenterLab\Token;

use Illuminate\Support\Manager as BaseManager;
use Illuminate\Contracts\Cache\Repository;
use AgenterLab\Token\Exceptions\TokenNotFoundException;
use AgenterLab\Token\Exceptions\TokenExpiredException;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;
use Illuminate\Encryption\Encrypter;

class TokenManager
{
    const MAX_ID_FACTOR = 4095;

    const DEFAULT_TTL = 900;

    /**
     * @var \Illuminate\Contracts\Cache\Store
     */
    private $store;

    /**
     * The Hasher implementation.
     *
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var int
     */
    private $instanceId;

    /**
     * @param int $instanceId
     * @param \Illuminate\Contracts\Cache\Repository $store
     * @param \Illuminate\Contracts\Hashing\Hasher $hasher
     * @param array $config
     */
    function __construct(
        int $instanceId,
        Repository $store,
        Hasher $hasher,
        array $config = []
    ) {

       $this->instanceId = $instanceId;
       $this->store = $store;
       $this->hasher = $hasher;
       $this->config = $config;
    }

    /**
     * Create token
     * 
     * @param string $type type of token
     * @param mixed $payload Token payload
     * @param int $owner
     * 
     * @return Token
     */
    public function create(string $type, $payload, int $owner = 0) {

        $id = $this->getId();
        $data = is_array($payload) ? $payload : [$payload];
        $data[] = $id;

        list($data, $ttl, $expireAt) = $this->format($type, $data);

        $token = self::encodeUrlSafe(app('encrypter')->encryptString($data));

        $this->store->put($type . '_' . $id, $token, $ttl);

        $token = new Token(
            $id,
            $type, 
            $owner, 
            $ttl, 
            $expireAt, 
            $token,
            $payload
        );
        return $token;
    }

    /**
     * Validate token 
     * 
     * @param string $type
     * @param string $token
     * @param bool $strict Check in cache
     * 
     * @return \AgenterLab\Token\Token
     * @throws TokenNotFoundException
     * @throws TokenExpiredException
     */
    public function validate(string $type, string $token, bool $strict = false) {

        $decrypted = app('encrypter')->decryptString(
            self::decodeUrlSafe($token)
        );

        $tokenParts = explode('|', $decrypted);
        $expireAt = array_pop($tokenParts);
        $tokenType = array_pop($tokenParts);
        $tokenId = array_pop($tokenParts);

        if ($type != $tokenType) {
            throw new TokenNotFoundException('Token type invalid');
        }

        $ttl =  $expireAt - Token::now();

        if ( $ttl <= 0 ) {
            throw new TokenExpiredException;
        }

        if ($strict) {

            $exists = $this->store->get($type . '_' . $tokenId);

            if (empty($exists)) {
                throw new TokenNotFoundException;
            }
        }

        $tokenParts = count($tokenParts) == 1 ? $tokenParts[0] : $tokenParts;

        $token = new Token(
            $tokenId,
            $tokenType, 
            0, 
            $ttl, 
            $expireAt, 
            $token,
            $tokenParts
        );
        return $token;
    }

    /**
     * Remove token
     * 
     * @param string $key
     */
    public function remove(string $key) {
        $this->store->forget($key);
    }

    /**
     * Get ID
     */
    private function getId(): int {

        $time = Token::now();

        $increment = $this->store->increment('auto_id_' . $this->instanceId);

        if ($increment == self::MAX_ID_FACTOR) {
            $this->store->forget('auto_id_' . $this->instanceId);
        }

        $result = ($this->instanceId << 52) | ($time << 12) | ($increment<<0);

        return $result;
    }

    /**
    * Converts a base64 encode url safe
    *
    * @param string $str
    * @return string
    */

    public static function encodeUrlSafe($str)
    {
        return str_replace('=', '', strtr($str, '+/', '-_'));
    }

    /**
    * Converts a base64 decode url safe
    *
    * @param string $str
    * @return string
    */

    public static function decodeUrlSafe($str)
    {
        if ($remainder = strlen($str) % 4) {
            $str .= str_repeat('=', 4 - $remainder);
        }

        $str = strtr($str, '-_', '+/');
        return $str;
    }

    /**
     * Encrypt using public key
     * 
     * @param string $type
     * @param mixed $payload
     * @param string $publicKey
     * @param int $owner
     * 
     * @return \AgenterLab\Token\Token
     */
    public function encrypt(string $type, $payload, string $publicKey, int $owner = 0) {

        $id = $this->getId();
        $data = is_array($payload) ? $payload : [$payload];
        $data[] = $id;
        list($data, $ttl, $expireAt) = $this->format($type, $data);

        $key = Encrypter::generateKey($this->config['cipher']);
        $keyToken = self::encodeUrlSafe((new Encrypter($key, $this->config['cipher']))->encryptString($data));

    
        $cryptText = '';
        openssl_public_encrypt ($key, $cryptText, $publicKey);
        $cryptText = self::encodeUrlSafe(base64_encode($cryptText));

        $token = $cryptText . '.' . $keyToken;

        $token = new Token(
            $id,
            $type, 
            $owner, 
            $ttl, 
            $expireAt, 
            $token,
            $payload
        );
        return $token;
    }

     /**
     * Decrypt using public key
     * 
     * @param string $type
     * @param string $data
     * @param string $privateKey
     * 
     * @return \AgenterLab\Token\Token
     * @throws TokenNotFoundException
     * @throws TokenExpiredException
     */
    public function decrypt(string $type, string $cryptText, string $privateKey) {

        $parts = explode('.', $cryptText);

        if (count($parts) != 2) {
            throw new TokenNotFoundException;
        }

        $cryptText = base64_decode(self::decodeUrlSafe($parts[0]));
  
        $key = null;
        $success = openssl_private_decrypt($cryptText, $key, $privateKey);

        if (!$success) {
            throw new TokenNotFoundException;
        }

        $decrypted = self::encodeUrlSafe((new Encrypter($key, $this->config['cipher']))->decryptString($parts[1]));

        $tokenParts = explode('|', $decrypted);
        $expireAt = array_pop($tokenParts);
        $tokenType = array_pop($tokenParts);

        if ($type != $tokenType) {
            throw new TokenNotFoundException('Token type invalid');
        }

        $ttl =  $expireAt - Token::now();

        if ( $ttl <= 0 ) {
            throw new TokenExpiredException;
        }

        $tokenParts = count($tokenParts) == 1 ? $tokenParts[0] : $tokenParts;

        $token = new Token(
            $tokenId,
            $tokenType, 
            0, 
            $ttl, 
            $expireAt, 
            $token,
            $tokenParts
        );
        return $token;
    }

    /**
     * Create a new token for the user.
     * 
     * @param string $type
     * @param string $key
     * @param string $code
     * @param int $userId
     *
     * @return string
     */
    public function hash(string $type, $key, $code, $userId = 0)
    {
        $ttl = $this->getTTL($type);
        $time = Token::now();
        $expireAt = $time + $ttl;

        $token = hash_hmac('sha256', Str::random(40), $this->config['hash_key']);
        $hash = $this->hasher->make($token. $code);

        $this->store->put($type . '_' . $key, $hash, $ttl);

        return new Token(
            $time,
            $type, 
            $userId, 
            $ttl, 
            $expireAt, 
            $token,
            $key
        );
    }

    /**
     * Check Hash
     */
    public function check(string $key, string $token) {

        $exists = $this->store->get($key);

        if (empty($exists)) {
            throw new TokenNotFoundException;
        }

        $check = $this->hasher->check($token, $exists);

        if (!$check) {
            throw new TokenNotFoundException('Token invalid');
        }

        return true;
    }

    /**
     * format payload
     * 
     * @return array
     */
    private function format(string $type, array $data): array {

        $ttl = $this->getTTL($type);
        
        $time = Token::now();
        $expireAt = $time + $ttl;

        $data[] = $type;
        $data[] = $expireAt;
        $data = implode('|', $data);

        return [$data, $ttl, $expireAt];
    }

    /**
     * Get ttl
     * 
     * @param string $type
     * @return int
     */
    private function getTTL(string $type) {
        $config = $this->config[$type] ?? [];
        $ttl = $config['ttl'] ?? $this->config['ttl'] ?? self::DEFAULT_TTL;

        return $ttl;
    }
}