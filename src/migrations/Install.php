<?php

namespace arifje\loginwithemailcode\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%loginwithemailcode_tokens}}')) {
            $this->createTable('{{%loginwithemailcode_tokens}}', [
                'id' => $this->primaryKey(),
                'userId' => $this->integer()->notNull(),
                'type' => $this->string(20)->notNull(),
                'selector' => $this->string(64)->notNull(),
                'verifierHash' => $this->string(255)->notNull(),
                'redirect' => $this->string(2048),
                'expiresAt' => $this->dateTime()->notNull(),
                'usedAt' => $this->dateTime(),
                'attempts' => $this->integer()->notNull()->defaultValue(0),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        $this->createIndex('idx-loginwithemailcode-tokens-selector', '{{%loginwithemailcode_tokens}}', ['selector'], true);
        $this->createIndex('idx-loginwithemailcode-tokens-user-type-expires', '{{%loginwithemailcode_tokens}}', ['userId', 'type', 'expiresAt']);
        $this->createIndex('idx-loginwithemailcode-tokens-expires-used', '{{%loginwithemailcode_tokens}}', ['expiresAt', 'usedAt']);
        $this->addForeignKey('fk-loginwithemailcode-tokens-user', '{{%loginwithemailcode_tokens}}', ['userId'], '{{%users}}', ['id'], 'CASCADE');

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->tableExists('{{%loginwithemailcode_tokens}}')) {
            $this->dropTable('{{%loginwithemailcode_tokens}}');
        }

        return true;
    }
}
