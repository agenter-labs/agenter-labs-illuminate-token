<?php

namespace AgenterLab\Token;

use Illuminate\Contracts\Support\Responsable;

class Token implements Responsable
{

    /**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    private $owner;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $token;

    /**
     * @var int
     */
    private $ttl;

    /**
     * @var mixed
     */
    private $payload;

    /**
     * @var int
     */
    private $expireAt;


    /**
     * Construct
     */
    function __construct(
        int $id, 
        string $type, 
        int $owner, 
        int $ttl, 
        int $expireAt, 
        string $token, 
        $payload = ''
        ) {

        $this->id = $id;
        $this->type = $type;
        $this->owner = $owner;
        $this->ttl = $ttl;
        $this->expireAt = $expireAt;
        $this->payload = $payload;
        $this->token = $token;
    }

    /**
     * Get id
     */
    public function getId() {

        return $this->id;
    }

    /**
     * Get token
     */
    public function getToken() {

        return $this->token;
    }

    /**
     * Get Expire in seconds
     */
    public function getExpireIn() {
        if (empty($this->ttl)) {
            return 0;
        }

        $time = time();
        $delta = $this->expireAt - $time;

        if ($delta < 0) {
            return 0;
        }

        return $time + $delta;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function toResponse($request)
    {
        return [
            'ttl' => $this->ttl,
            'token' => $this->token,
            'expire_in' => $this->getExpireIn()
        ];
    }
}