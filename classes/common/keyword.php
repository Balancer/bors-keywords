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
			'keyword_forms',
			'modify_time',
			'targets_count',
			'target_containers_count',
			'description',
			'synonym_to_id' => 'synonym_to',
			'tree_map',
			'is_autosearch_original',
			'is_autosearch_normalized',
			'is_moderated',

			'modify_time',
		);
	}

	static function normalize($words, $sort = false)
	{
		$words = str_replace('_', ' ', $words);
		$keywords = array();
		$Stemmer = new Lingua_Stem_Ru();

		$words = preg_replace('/[^\wа-яА-ЯёЁ\-]+/u', ' ', $words);

		$words = array_filter(explode(' ', $words));
		foreach($words as $word)
		{
			if(preg_match('/[a-zа-яё]/u', $word) || strlen($word) < 3)
				// если есть строчные буквы или слово коротке- то нормируем
				$keywords[] = $Stemmer->stem_word($word);
			else
				// если все прописные - оставляем как есть, это аббревиатура
				$keywords[] = $word;
		}

		if($sort)
			sort($keywords);

		return join(' ', $keywords);
	}

	static function loader($words)
	{
		$keyword = common_keyword::normalize(trim($words), true);
		$x = bors_find_first('common_keyword', array('keyword' => $keyword));
		if(!$x)
		{
			$x = bors_new('common_keyword', array(
				'title' => $words,
				'keyword' => $keyword,
				'keyword_original' => $words,
				'targets_count' => 0,
			));
		}

		if($x->synonym())
			$x = $x->synonym();
		else
			$x->set_attr('keyword_normalized', $keyword);

		return $x;
	}

	function url() { return config('tags_root_url', 'http://forums.balancer.ru/tags').'/'.trim($this->title()).'/'; }

	static function keyword_search_reindex($kw, $set = false, $in_titles = false, $morfology = false)
	{
		$count = 0;
		require_once('inc/search/sphinx.php');

		$xs = bors_search_sphinx($kw, array(
			'indexes' => 'topic_keywords' . ($in_titles ? ',topic_titles' : ''),
			'only_objects' => true,
//			'page' => 1,
//			'per_page' => 10,
			'persistent_instance' => true,
			'exactly' => true,
			'filter' => array('forum_id<>' => array(37)),
		));

//		print_dd($xs);
//		exit();

		if(!is_array($xs))
			return 0;

		$ucase = ($kw == bors_upper($kw)); // тэг в верхнем регистре. Сокращение/аббревиатура.

		foreach($xs as $x)
		{
//			echo "Test {$x->debug_title()} for {$x->keywords_string()}\n";
			if(in_array($kw, $x->keywords()))
			{
				if($set)
				{
					common_keyword_bind::add($x);
					$count++;
				}

				continue;
			}

			if($ucase && strpos($x->title(), $kw) === false && strpos($x->description(), $kw) === false)
			{
//				echo "Remove tag $kw from {$x->debug_title()}, kw={$x->keywords_string()}\n";
//				$x->remove_keyword($kw, true);
//				common_keyword_bind::add($x);
				continue;
			}

			if($morfology || stripos($x->title(), $kw) !== false || stripos($x->description(), $kw) !== false)
			{
				echo "Add tag $kw to {$x->debug_title()}, kw={$x->keywords_string()}\n";
//				if($in_titles) continue;
				$x->add_keyword($kw, true);
				common_keyword_bind::add($x);
				$count++;
				continue;
			}
		}

		bors()->changed_save();
		return $count;
	}

	static function best_forum($keywords_string, $forum_id = 12, $is_debug = false)
	{
		$fids = array();
		foreach(explode(',', $keywords_string) as $tag)
		{
			common_keyword::keyword_search_reindex($tag, true);
			$kw = common_keyword::loader($tag);

//			echo ">>>$tag -> {$kw->title()}\n";

			$bindings = objects_array('common_keyword_bind', array(
				'keyword_id' => $kw->id(),
				'group' => 'target_forum_id',
				'order' => 'count(*) DESC',
				'*set' => 'COUNT(*) AS total',
				'limit' => 10,
			));

			$bindings_total = count($bindings);

			foreach($bindings as $bind)
			{
				$weight = sqrt($bind->total()) / ($bindings_total+1);
//				if($is_debug) echo "$tag [{$bind->target_forum()->debug_title()}]: {$bind->total()}\n";
				@$fids[$bind->target_forum_id()] += sqrt($bind->total());
			}
		}

		asort($fids);

//		print_d($fids);

		if($fids)
			$forum_id = array_pop(array_keys($fids));

		return $forum_id;
	}

	static function best_topic($keywords_string, $topic_id, $is_debug = false, $limit = false, $weight_limit = false, $skip_tids = array())
	{
		$ids = array();
		foreach(explode(',', $keywords_string) as $tag)
		{
			if($is_debug) echo "\t======================\n\tFind topics for $tag\n\t----------------------\n";

			if(!$limit)
				common_keyword::keyword_search_reindex($tag, true);

			$kw = common_keyword::loader($tag);
			$kw_norm = $kw->keyword_normalized();
			if($is_debug) echo "\tFind for {$kw->debug_title()} [{$kw_norm}]\n";
			$bindings = bors_find_all('common_keyword_bind', array(
				'keyword_id' => $kw->id(),
				'target_container_class_name IN' => array('balancer_board_topic', 'forum_topic'),
				'target_container_object_id NOT IN' => $skip_tids,
				'group' => 'target_container_object_id',
				'order' => 'count(*) DESC',
				'*set' => 'COUNT(*) AS items_count',
				'limit' => 50,
			));

			$bindings_total = count($bindings);

			bors_objects_targets_preload($bindings, 'target_container_class_name', 'target_container_object_id', 'container');
			bors_objects_targets_preload($bindings, 'target_class_name', 'target_object_id', 'target');

			if($is_debug) echo "\tbindings total=$bindings_total\n";
			$count = 0;
			foreach($bindings as $bind)
			{
				$topic  = $bind->container_or_target();
				$target = $bind->target();
				if(!$topic)
				{
					if($is_debug) echo " *** Unknown container for bind {$bind->id()} (target=".object_property($target, 'debug_title').")\n";
					debug_hidden_log('keywords_index_error', "Unknown target or container for bind {$bind->id()}");
					continue;
				}

				$in_title = 10;
				if(!$limit && !self::object_keywords_check($topic, $kw_norm, true, $is_debug))
				{
					$in_title = 5;
//					if(!self::object_keywords_check($target, $kw_norm, true, $is_debug))
//						continue;
				}

				$weight = $in_title * sqrt($bind->items_count()) / ($bindings_total+1);
//				$weight *= sqrt(sqrt());
				if($is_debug && $count++<10)
				{
					 echo "\t\tFound $tag in {$target->debug_title()} [{$target->keywords_string()}]\n";
					 if(!bors_eq($topic, $target))
					 	echo "\t\t\tin {$topic->debug_title()} [{$target->keywords_string()}]\n";
					 echo "\t\t\tw={$weight}, it={$in_title}, c={$bind->items_count()}\n";
				}

				@$ids[$bind->target_container_object_id()] += $weight;
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
		{
			if($limit)
			{
				if($is_debug)
					var_dump(array_slice(array_reverse($ids), 0, $limit));

				if($weight_limit)
					$ids = array_filter($ids, create_function('$x', 'return $x>'.str_replace(',','.',$weight_limit.';')));

				$topic_id = array_slice(array_reverse(array_keys($ids)), 0, $limit);
			}
			else
				$topic_id = array_pop(array_keys($ids));
		}

		$GLOBALS['__debug_last_topic_weight'] = @$ids[$topic_id];
		return $topic_id;
	}

	static function object_keywords_check($object, $keyword_norm, $rebind = true, $is_debug = false)
	{
		if(!$object)
			return false;

		$object_keywords_norm = array();
		foreach($object->keywords() as $k)
			$object_keywords_norm[] = self::normalize($k);

		if(in_array($keyword_norm, $object_keywords_norm))
			return true;

		foreach($object->keywords() as $k)
			if(strcasecmp($k, $keyword_norm) == 0)
				return true;

		if(!$rebind)
		{
//			if($is_debug) echo " *** Not exists keyword '$keyword_norm' in keywords '".join(',', $object_keywords_norm)."' for object {$object->debug_title()}\n";
//			debug_hidden_log('keywords_index_error', "Not exists keyword '$keyword_norm' in '".join(',', $object_keywords_norm)."'");
			return false;
		}

//		if($is_debug) echo " *** Not exists keyword '$keyword_norm' in '".join(',', $object_keywords_norm)."'. Try rebind\n";
		common_keyword_bind::add($object);

		return self::object_keywords_check($object, $keyword_norm, false, $is_debug);
	}

	static function compare_eq($kws1, $kws2) { return self::normalize($kws1, true) == self::normalize($kws2, true); }

	function change_synonym()
	{
		if(!$this->synonym_to_id())
			return 0;

		$syn = object_load('common_keyword', $this->synonym_to_id());
		foreach(objects_array('common_keyword_bind', array('keyword_id' => $this->id())) as $bind)
		{
			$obj = $bind->target();
//			echo "{$obj->debug_title()}: change {$this->title()} to {$syn->title()}\n";
			$obj->remove_keyword($this->title(), true);
			$obj->add_keyword($syn->title(), true);
		}

		bors()->changed_save();
		$this->set_targets_count(objects_count('common_keyword_bind', array('keyword_id' => $this->id())));
		$count = $syn->set_targets_count(objects_count('common_keyword_bind', array('keyword_id' => $syn->id())));

		return $count;
	}

	static function linkify($keywords, $base_keywords = '', $join_char = ', ', $no_style = false, $base = 'http://forums.balancer.ru/tags')
	{
		$base = config('tags_root_url', $base);
		$result = array();
		foreach($keywords as $key)
		{
			$kws = array_map('urlencode', array_filter(explode(',', $key.','.$base_keywords)));
			$kws = join("/", $kws);
			if($no_style)
				$result[] = "<a href=\"{$base}/{$kws}/\">".trim($key)."</a>";
			else
			{
				$k = self::loader($key);
				$result[] = "<a style=\"font-size:".intval(10+sqrt($k->targets_count())/3)."px;\" href=\"{$base}/"
					.$kws."/\">".trim($key)."</a>";
			}
		}

		return join($join_char, $result);
	}

	// Возвращает _текстовые_ тэги в виде массива для указанного объекта
	static function all($object)
	{
		$bindings = bors_find_all('common_keyword_bind', array(
			'target_class_id' => $object->class_id(),
			'target_object_id' => $object->id(),
		));

		$keyword_ids = bors_field_array_extract($bindings, 'keyword_id');
		$keywords = bors_find_all(__CLASS__, array('id IN' => $keyword_ids));
		return bors_field_array_extract($keywords, 'title');
	}

	// Возвращает все объекты, привязанные к данному тэгу
	static function find_all_objects($tag, $where = array())
	{
		$data = array(
			'keyword_id' => $tag->id(),
		);

		$bindings = bors_find_all('common_keyword_bind', array_merge($data, $where));
		return bors_field_array_extract($bindings, 'target');
	}

	// Возвращает id всех объектов, привязанные к данному тэгу
	static function find_all_object_ids($tag, $where = array())
	{
		$data = array(
			'keyword_id' => $tag->id(),
		);

		$bindings = bors_find_all('common_keyword_bind', array_merge($data, $where));
		return bors_field_array_extract($bindings, 'target_object_id');
	}

	// Возвращает количество всех объектов, привязанные к данному тэгу
	static function tag_targets_count($tag, $where = array())
	{
		$data = array(
			'keyword_id' => object_property($tag, 'id'),
		);

		return objects_count('common_keyword_bind', array_merge($data, $where));
	}

	// Возвращает все тэги, привязанные к данному объекту
	static function find_all_tags_string($object, $where = array())
	{
		$data = array(
			'target_class_id IN' => array($object->extends_class_id(), $object->class_id()),
			'target_object_id' => $object->id(),
		);

		$bindings = bors_find_all('common_keyword_bind', array_merge($data, $where));
		$tag_ids = bors_field_array_extract($bindings, 'keyword_id');
		$tags = bors_find_all(__CLASS__, array('id IN' => $tag_ids));
		return join(', ', bors_field_array_extract($tags, 'title'));
	}

	function synonym()
	{
		if(!$this->get('synonym_to_id'))
			return NULL;

		return bors_load(__CLASS__, $this->synonym_to_id());
	}

	static function add($object, $was_auto = false, $tag = NULL)
	{
		return common_keyword_bind::add($object, $was_auto, $tag);
	}
}
