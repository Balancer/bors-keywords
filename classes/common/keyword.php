<?php

require_once('classes/inc/text/Stem_ru-'.config('internal_charset').'.php');

class common_keyword extends base_page_db
{
	function main_db(){ return config('main_bors_db'); }
	function main_table(){ return 'bors_keywords'; }

    function main_table_fields()
	{
		return array(
			'id',
			'keyword',
			'title' => 'keyword_original',
			'keyword_original',
			'modify_time',
			'targets_count',
			'description',
			'synonym_to_id' => 'synonym_to',
		);
	}

	static function normalize($words)
	{
		$keywords = array();
		$Stemmer = &new Lingua_Stem_Ru();

		foreach(explode(' ', bors_lower($words)) as $word)
			if($word)
				$keywords[] = $Stemmer->stem_word($word);

		sort($keywords);

		return join(' ', $keywords);
	}

	static function loader($words)
	{
		$keyword = common_keyword::normalize($words);
		$x = objects_first('common_keyword', array('keyword' => $keyword));
		if(!$x)
		{
			$x = object_new_instance('common_keyword', array(
				'keyword' => $keyword,
				'keyword_original' => $words,
				'targets_count' => 0,
			));
		}

		return $x;
	}

	function url() { return 'http://forums.balancer.ru/tags/'.trim($this->title()).'/'; }
}
