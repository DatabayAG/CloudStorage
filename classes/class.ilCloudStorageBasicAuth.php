<?php

declare(strict_types=1);

/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilCloudStorageOAuth2
 *
 * @author  Stefan Schneider <eqsoft4@gmail.com>
 */
class ilCloudStorageBasicAuth
{

    const DB_TABLE_NAME = 'rep_robj_xcls_bauth';
   
    private ?int $conn_id = 0;

    private ?int $user_id = 0;
    
    private ?string $username = '';

    private ?string $password = '';


    private function store(): void {
        global $DIC;
        $query = $DIC->database()->query("SELECT user_id FROM " . self::DB_TABLE_NAME . " WHERE conn_id = " . $this->getConnId() . " AND user_id = " . $this->getUserId());
        $ret = $DIC->database()->fetchAssoc($query);
        if (!is_null($ret)) {
            $DIC->database()->manipulateF(
                'UPDATE ' . self::DB_TABLE_NAME . ' SET username = %s, password = %s, valid_through = %s WHERE conn_id = %s AND user_id = %s',
                array('text', 'text', 'integer', 'integer', 'integer'),
                array($this->getUsername(), $this->getPassword(), $this->getConnId(), $this->getUserId())
            );
        } else {
            $DIC->database()->manipulateF(
                'INSERT INTO ' . self::DB_TABLE_NAME . ' (conn_id, user_id, username, password) VALUES (%s, %s, %s, %s)',
                array('integer', 'integer', 'text', 'text', 'integer'),
                array($this->getConnId(), $this->getUserId(), $this->getUsername(), $this->getPassword())
            );
        }
    }
    
    public function storeAccount(string $username, string $password, int $conn_id)
    {
        $this->setConnId($conn_id);
        $this->setUsername($username);
        $this->setPassword($password);
        $this->store();
    }

    public static function getAccount(int $conn_id, int $user_id = 0): ilCloudStorageBasicAuth
    {
        global $DIC;
        if ($user_id == 0) {
            global $ilUser;
            $user_id = $ilUser->getId();
        }
        $query = $DIC->database()->query("SELECT * FROM " . self::DB_TABLE_NAME . " WHERE conn_id = " . $conn_id . " AND user_id = " . $user_id);
        $ret = $DIC->database()->fetchAssoc($query);
        if (is_null($ret)) {
            $token = new self();
            $token->setConnId($conn_id);
            $token->setUserId($user_id);
        } else {
            $token = new self();
            $token->setConnId($conn_id);
            $token->setUserId($user_id);
            $token->setUsername($ret['username']);
            $token->setPassword($ret['password']);
        }
        return $token;
    }

    public static function deleteBasicAuth(int $conn_id, int $user_id = 0): void
    {
        global $DIC;
        if ($user_id == 0) {
            global $ilUser;
            $user_id = $ilUser->getId();
        }
        $DIC->database()->manipulate("DELETE FROM " . self::DB_TABLE_NAME . " WHERE conn_id = " . $conn_id . " AND user_id = " . $user_id);
    }


    public function setConnId(int $conn_id): void
    {
        $this->conn_id = $conn_id;
    }

    public function getConnId(): int
    {
        return $this->conn_id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

}