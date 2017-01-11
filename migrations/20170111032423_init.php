<?php

use Phinx\Migration\AbstractMigration;

class Init extends AbstractMigration
{
    public function change()
    {
		$this->table('bors_keywords', ['id' => false, 'primary_key' => 'id'])
			->addColumn('id', 'integer', ['signed' => false, 'identity' => true, 'limit' => 10])
			->addColumn('keyword', 'string')
			->addColumn('description', 'text', ['null' => true])
			->addColumn('keyword_original', 'string')
			->addColumn('keyword_forms', 'text')
			->addColumn('targets_count', 'integer', ['signed' => false, 'limit' => 10, 'default' => '0'])
			->addColumn('target_containers_count', 'integer', ['signed' => false, 'limit' => 10, 'default' => '0'])
			->addColumn('synonym_to', 'integer', ['signed' => false, 'length'=>10, 'null'=>true])
			->addColumn('tree_map', 'string')
			->addColumn('is_autosearch_original', 'boolean')
			->addColumn('is_autosearch_normalized', 'boolean')
			->addColumn('is_moderated', 'boolean')

			->addColumn('modify_time', 'integer')

			->addForeignKey('synonym_to', 'bors_keywords', 'id', ['delete'=> 'SET_NULL', 'update'=> 'CASCADE'])

			->addIndex('targets_count')
			->addIndex('keyword')
			->addIndex('modify_time')

			->create();
    }
}
