<?php

require_once("include/classes/text/Stem_ru-utf8.php");
	
class common_keyword extends base_page_db
{
	function main_db_storage(){ return 'BORS'; }
	function main_table_storage(){ return 'keywords'; }

    function main_table_fields()
	{
		return array(
			'id',
			'title' => 'keyword',
			'keyword_original',
			'modify_time',
			'targets_count',
		);
	}

	static function normalize($words)
	{
		$keywords = array();
		$Stemmer = &new Lingua_Stem_Ru();

		foreach(explode(' ', strtolower($words)) as $word)
			if($word)
				$keywords[] = $Stemmer->stem_word($word);

		return join(' ', $keywords);
	}

	static function loader($words)
	{
		$keyword = common_keyword::normalize($words);
		$x = objects_first('common_keyword', array('keyword' => $keyword));
		if(!$x)
		{
			$x = object_new_instance('common_keyword', array(
				'title' => $keyword,
				'keyword_original' => $words,
			));
		}
		
		return $x;
	}
}
