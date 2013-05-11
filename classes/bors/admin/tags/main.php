<?php

class bors_admin_tags_main extends bors_admin_paginated
{
	function title() { return ec('Редактор тегов'); }

	function main_class() { return 'common_keyword'; }
	function order() { return '-modify_time'; }

	function where()
	{
		return array_merge(parent::where(), array(
			'(target_containers_count<> 0 OR targets_count<>0)'
		));
	}

	function items_per_page() { return 50; }
}
