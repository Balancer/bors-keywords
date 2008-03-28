<?
	
class common_keyword extends base_page_db
{
	function main_db_storage(){ return 'common'; }
	function main_table_storage(){ return 'keywords'; }
	
    function field_title_storage() { return 'keyword(id)'; }
	function field_create_time_storage() { return 'create_time(id)'; }
	function field_modify_time_storage() { return 'modify_time(id)'; }

	function resource_ids()
	{
		$result = array();

		foreach($this->db->get_array("SELECT * FROM authors_index WHERE author_id = ".$this->id()) as $x)
			$result[] = $x['class_name'].'://'.$x['class_id'];

		return $result;
	}

	function find_by_name($keyword)
	{
		$db = &new DataBase(common_keyword::main_db_storage());
		return intval($db->get("SELECT id FROM keywords WHERE keyword='".addslashes(trim($keyword))."' LIMIT 1"));
	}

	function store_by_name($keyword)
	{
		$keyword_id = common_keyword::find_by_name($keyword);
		if($keyword_id)
			return $keyword_id;
		
		$db = &new DataBase(common_keyword::main_db_storage());
		$db->insert('keywords', array(
			'keyword' => trim($keyword),
			'int create_time' => time(),
			'int modify_time' => time(),
		));
		
		return intval($db->last_id());
	}
}
