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

	static function keyword_search_reindex($kw)
	{
		require_once('inc/search/sphinx.php');

		$xs = bors_search_sphinx($kw, array(
			'indexes' => 'topics',
			'only_objects' => true,
			'page' => 1,
			'per_page' => 10,
			'persistent_instance' => true,
			'exactly' => true,
			'filter' => array('forum_id<>' => array(37)),
		));

		if(!is_array($xs))
			return;

		foreach($xs as $x)
		{
			if(in_array($kw, $x->keywords()))
				continue;

			$x->add_keyword($kw, true);
			common_keyword_bind::add($x);
		}

		bors()->changed_save();
	}

	static function best_forum($keywords_string)
	{
		$forum_id = 12;

		$fids = array();
		foreach(explode(',', $keywords_string) as $tag)
		{
			common_keyword::keyword_search_reindex($tag);
			$kw = common_keyword::loader($tag);

//			echo ">>>$tag -> {$kw->title()}\n";

			$kwbs = objects_array('common_keyword_bind', array(
				'keyword_id' => $kw->id(),
				'group' => 'target_forum_id',
				'order' => 'count(*) DESC',
				'select' => array('COUNT(*) AS total'),
				'limit' => 10,
			));

			foreach($kwbs as $kwb)
			{
//				echo "$tag [{$kwb->target_forum()->debug_title()}]: {$kwb->total()}\n";
				@$fids[$kwb->target_forum_id()] += sqrt($kwb->total());
			}
		}

		asort($fids);

//		print_d($fids);

		if($fids)
			$forum_id = array_pop(array_keys($fids));

		return $forum_id;
	}

	static function best_topic($keywords_string, $topic_id)
	{
		$ids = array();
		foreach(explode(',', $keywords_string) as $tag)
		{
			common_keyword::keyword_search_reindex($tag);
			$kw = common_keyword::loader($tag);

			$kwbs = objects_array('common_keyword_bind', array(
				'keyword_id' => $kw->id(),
				'target_container_class_name' => 'balancer_board_topic',
				'group' => 'target_container_object_id',
				'order' => 'count(*) DESC',
				'select' => array('COUNT(*) AS total'),
				'limit' => 20,
			));

			foreach($kwbs as $kwb)
			{
				$topic = object_load('balancer_board_topic', $kwb->target_container_object_id());
				echo "$tag [{$kwb->total()}, ".round(sqrt($kwb->total()), 1).", ".round(log($kwb->total())+1, 1)."]: {$topic->debug_title()}\n";
				@$ids[$kwb->target_container_object_id()] += log($kwb->total())+1;
			}
		}

		asort($ids);

		echo "\n=== sorted result: ===\n";
		foreach($ids as $id => $count)
		{
			$topic = object_load('balancer_board_topic', $id);
			echo "{$topic->debug_title()}: $count\n";
		}

		if($ids)
			$topic_id = array_pop(array_keys($ids));

		return $topic_id;
	}
}
