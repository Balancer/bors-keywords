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
		$Stemmer = new Lingua_Stem_Ru();

		foreach(explode(' ', bors_lower($words)) as $word)
			if($word)
				$keywords[] = $Stemmer->stem_word($word);

		sort($keywords);

		return join(' ', $keywords);
	}

	static function loader($words)
	{
		$keyword = common_keyword::normalize(trim($words));
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

	function url() { return config('tags_root_url', 'http://forums.balancer.ru/tags').'/'.trim($this->title()).'/'; }

	static function keyword_search_reindex($kw)
	{
		$count = 0;
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

//		print_r($xs);

		if(!is_array($xs))
			return 0;

		$ucase = ($kw == bors_upper($kw)); // тэг в верхнем регистре. Сокращение/аббревиатура.

		foreach($xs as $x)
		{
			if(in_array($kw, $x->keywords()))
				continue;

			if($ucase && strpos($x->title(), $kw) === false && strpos($x->description(), $kw) === false)
			{
//				echo "Remove tag $kw from {$x->debug_title()}, kw={$x->keywords_string()}\n";
//				$x->remove_keyword($kw, true);
//				common_keyword_bind::add($x);
				continue;
			}

			if(stripos($x->title(), $kw) !== false || stripos($x->description(), $kw) !== false)
			{
				echo "Add tag $kw to {$x->debug_title()}, kw={$x->keywords_string()}\n";
				$x->add_keyword($kw, true);
				common_keyword_bind::add($x);
				$count++;
				continue;
			}
		}

		bors()->changed_save();
		return $count;
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
//				echo "$tag [{$kwb->total()}, ".round(sqrt($kwb->total()), 1).", ".round(log($kwb->total())+1, 1)."]: {$topic->debug_title()}\n";
				@$ids[$kwb->target_container_object_id()] += sqrt($kwb->total());
			}
		}

		asort($ids);
/*
		echo "\n=== sorted result: ===\n";
		foreach($ids as $id => $count)
		{
			$topic = object_load('balancer_board_topic', $id);
			echo "{$topic->debug_title()}: $count\n";
		}
*/
		if($ids)
			$topic_id = array_pop(array_keys($ids));

		return $topic_id;
	}

	static function compare_eq($kws1, $kws2) { return self::normalize($kws1) == self::normalize($kws2); }

	function change_synonym()
	{
		if(!$this->synonym_to_id())
			return 0;

		$syn = object_load('common_keyword', $this->synonym_to_id());
		foreach(objects_array('common_keyword_bind', array('keyword_id' => $this->id())) as $bind)
		{
			$obj = $bind->target();
			echo "{$obj->debug_title()}: change {$this->title()} to {$syn->title()}\n";
			$obj->remove_keyword($this->title(), true);
			$obj->add_keyword($syn->title(), true);
		}

		bors()->changed_save();
		echo $this->set_targets_count(objects_count('common_keyword_bind', array('keyword_id' => $this->id())), true);
		echo $syn->set_targets_count(objects_count('common_keyword_bind', array('keyword_id' => $syn->id())), true);
	}

	static function linkify($keywords, $base_keywords = '')
	{
		$result = array();
		foreach($keywords as $key)
		{
			$k = self::loader($key);
			$result[] = "<a style=\"font-size:".intval(10+sqrt($k->targets_count())/3)."px;\" href=\"".config('tags_root_url', 'http://forums.balancer.ru/tags')."/".
				join("/", array_map('urlencode', explode(',', $key.','.$base_keywords)))
			."/\">".trim($key)."</a>";
		}
		return join(', ', $result);
	}
}
