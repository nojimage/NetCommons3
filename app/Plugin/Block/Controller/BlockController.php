<?php
/**
 * BlockControllerクラス
 *
 * <pre>
 * ブロック操作（ブロック追加、削除、ブロックテーマ、ブロック移動、グループ化）用コントローラ
 * </pre>
 *
 * @copyright     Copyright 2012, NetCommons Project
 * @package       App.Plugin.Controller
 * @author        Noriko Arai,Ryuji Masukawa
 * @since         v 3.0.0.0
 * @license       http://www.netcommons.org/license.txt  NetCommons License
 */
class BlockController extends BlockAppController {

	public $components = array('Block.BlockMove', 'CheckAuth' => array('allowAuth' => NC_AUTH_CHIEF, 'checkOrder' => array("request", "url")));
	public $uses = array('Block.BlockMoveOperation');

	public $nc_block = array();
	public $nc_page = array();

/**
 * 実行前処理
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function beforeFilter()
	{
		parent::beforeFilter();
		if(empty($this->request->params['requested']) && $this->action == 'add_block') {
			$this->CheckAuth->chkBlockId = false;
		} else if(($this->action == 'add_block' || $this->action == 'insert_row') && !empty($this->request->params['requested'])) {
			$this->CheckAuth->chkMovedPermanently = false;
		}
	}

/**
 * ブロック追加
 * ブロック操作 - ペースト、ショットカット作成処理
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function add_block() {
		$user_id = $this->Auth->user('id');
		$page = $this->nc_page;
		$page_id = $page['Page']['id'];
		$module_id = $this->request->data['module_id'];
		$show_count = $this->request->data['show_count'];
		$pre_page = $page;
		$copy_block_id = intval($this->Session->read('Blocks.'.'copy_block_id'));
		$copy_content_id = intval($this->Session->read('Blocks.'.'copy_content_id'));
		$shortcut_flag = isset($this->request->data['shortcut_flag']) ? $this->request->data['shortcut_flag'] : null;

		if(!empty($this->request->params['requested']) && !empty($copy_block_id)) {
			$block = $this->Block->findById($copy_block_id);	// 再取得
			$pre_page = $this->Page->findAuthById(intval($block['Block']['page_id']), $user_id);
			if(!$pre_page || $pre_page['Authority']['hierarchy'] < NC_AUTH_MIN_CHIEF) {
				$this->flash(__('Authority Error!  You do not have the privilege to access this page.'), null, 'add_block.001', '403');
				return;
			}
			$content = array('Content' => $this->nc_block['Content']);
			$ret_validator = $this->BlockMove->validatorRequestContent($content, $pre_page, $page);
			if($ret_validator !== true) {
				// error
				$this->flash($ret_validator, null, 'add_block.002', '400');
				return;
			}
		}

		if (!isset($this->request->data) || !isset($this->request->data['show_count']) || !isset($this->request->data['module_id'])
				|| !isset($this->request->data['page_id'])) {
			// Error
			$this->flash(__('Unauthorized request.<br />Please reload the page.'), null, 'add_block.003', '400');
			return;
		}

		if(!$page || $page['Page']['show_count'] != $show_count) {
			$this->flash(__d('block', 'Because of the possibility of inconsistency happening, update will not be executed. <br /> Please redraw and update again.'), null, 'add_block.004', '400');
			return;
		}

		$module = $this->Module->findById($module_id);
		if(!$module) {
			$this->flash(__('Unauthorized request.<br />Please reload the page.'), null, 'add_block.005', '400');
			return;
		}

		// TODO: そのmoduleが該当ルームに貼れるかどうかのチェックが必要。
		// グループ化ブロック（ショートカット）ならば、該当グループ内のmoduleのチェックが必要。
		// はりつけたあと、表示されませんで終わらす方法も？？？ -> グループ化ブロックはペースト不可

		if(isset($shortcut_flag) && $page['Page']['room_id'] == $content['Content']['room_id']) {
			// コンテンツのルームが同じならば、ルーム権限を付与していないショートカットへ
			$shortcut_flag = _OFF;
		}

		if(!empty($this->request->params['requested']) && !empty($copy_block_id)) {
				//(isset($shortcut_flag) || $pre_page['Page']['room_id'] != $content['Content']['room_id'] || !$content['Content']['is_master'])) {

			$master_content = $content;
			if(!$content['Content']['is_master']) {
				$master_content = $this->Content->findById($content['Content']['master_id']);
			}
		   /** ペースト、ショートカットのペースト,ショートカットの作成
			* ・権限が付与されていないショートカットのペーストか、権限が付与されていないショートカットの作成
			* 		Block.content_id 新規に取得しないで、ショートカット元のcontent_idを付与
			* 		Contentは追加しない。
			*  ・ペースト、ショートカットの作成（表示中のルーム権限より閲覧・編集権限を付与する。）
			* 		Contentは新規追加するが、ショートカット元のContentの中身(title,is_master, master_id,accept_flag,url)はコピー
			* 			room_idはショートカット先のroom_id
			*/
			if(($pre_page['Page']['room_id'] != $room_id || !$content['Content']['is_master']) &&
					$page['Page']['room_id'] == $master_content['Content']['room_id']) {
				// 権限が付与されているショートカット、または、ショートカットを元のルームに戻した。
				$ins_content = $master_content;
				$last_content_id = $master_content['Content']['id'];
			} else if((!isset($shortcut_flag) && $pre_page['Page']['room_id'] != $content['Content']['room_id']) ||
					$shortcut_flag === _OFF) {
				// 権限が付与されていないショートカットのペーストか、権限が付与されていないショートカットの作成
				$ins_content = $content;
				$last_content_id = $content['Content']['id'];
			} else {
				$ins_content = array(
					'Content' => array(
						'module_id' => $content['Content']['module_id'],
						'title' => $content['Content']['title'],
						'is_master' => ($shortcut_flag === _ON) ? _OFF : $content['Content']['is_master'],
						'room_id' => $page['Page']['room_id'],
						'accept_flag' => $content['Content']['accept_flag'],
						'url' => $content['Content']['url']
					)
				);
				if($shortcut_flag === _ON) {
					// 権限が付与されたショートカットの作成
					$ins_content['Content']['master_id'] = $content['Content']['id'];
				}
				$ins_ret = $this->Content->save($ins_content);
				if(!$ins_ret) {
					$this->flash(__('Failed to register the database, (%s).', 'contents'), null, 'add_block.006', '500');
					return;
				}
				$last_content_id = $this->Content->id;
			}
		} else {
			$ins_content = array(
				'Content' => array(
					'module_id' => $module['Module']['id'],
					'title' => $module['Module']['module_name'],
					'is_master' => _ON,
					'room_id' => $page['Page']['room_id'],
					'accept_flag' => NC_ACCEPT_FLAG_ON,
					'url' => ''
				)
			);
			$ins_ret = $this->Content->save($ins_content);
			if(!$ins_ret) {
				$this->flash(__('Failed to register the database, (%s).', 'contents'), null, 'add_block.007', '500');
				return;
			}
			$last_content_id = $this->Content->id;
		}

		if(!isset($ins_content['Content']['master_id'])) {
			if(!$this->Content->saveField('master_id', $last_content_id)) {
				$this->flash(__('Failed to update the database, (%s).', 'contents'), null, 'add_block.008', '500');
				return;
			}
		}

		$ins_block = array();
		$ins_block = $this->BlockMoveOperation->defaultBlock($ins_block);
		$ins_block['Block'] = array_merge($ins_block['Block'], array(
			'page_id' => $page['Page']['id'],
			'module_id' => $module['Module']['id'],
			'content_id' => $last_content_id,
			'controller_action' => $module['Module']['controller_action'],
			'theme_name' => '',
			'root_id' => 0,
			'parent_id' => 0,
			'thread_num' => 0,
			'col_num' => 1,
			'row_num' => 1
		));
		if(!empty($this->request->params['requested']) && !empty($copy_block_id)) {
			/** ペースト OR ショートカット作成
			 * 	移動元のBlockの中身(title, show_title, display_flag, display_from_date,display_to_date, theme_name, temp_name, leftmargin,
			 * 		rightmargin, topmargin,bottommargin,min_width_size,min_height_size)はコピー
			 */
			$ins_block['Block'] = array_merge($ins_block['Block'], array(
				'title' => $block['Block']['title'],
				'show_title' => $block['Block']['show_title'],
				'display_flag' => $block['Block']['display_flag'],
				'display_from_date' => $block['Block']['display_from_date'],
				'display_to_date' => $block['Block']['display_to_date'],
				'theme_name' => $block['Block']['theme_name'],
				'temp_name' => $block['Block']['temp_name'],
				'leftmargin' => $block['Block']['leftmargin'],
				'rightmargin' => $block['Block']['rightmargin'],
				'topmargin' => $block['Block']['topmargin'],
				'bottommargin' => $block['Block']['bottommargin'],
				'min_width_size' => $block['Block']['min_width_size'],
				'min_height_size' => $block['Block']['min_height_size'],
			));
		}

		$ins_ret = $this->Block->save($ins_block);
		if(!$ins_ret) {
			$this->flash(__('Failed to register the database, (%s).', 'blocks'), null, 'add_block.009', '500');
			return;
		}

		//root_idを再セット
		$last_id = $this->Block->id;
		if(!$this->Block->saveField('root_id', $last_id)) {
			$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'add_block.010', '500');
			return;
		}

		$ins_ret['Block']['id'] = $this->Block->id;
		$ins_ret['Block']['root_id'] = $this->Block->id;
		$ins_ret['Block']['row_num'] = 0;
		$inc_ret = $this->BlockMoveOperation->incrementRowNum($ins_ret);
		if(!$inc_ret) {
			$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'add_block.011', '500');
			return;
		}

		// 表示カウント++
		$this->Page->id = $page_id;
		if(!$this->Page->saveField('show_count', intval($show_count) + 1)) {
			$this->flash(__('Failed to update the database, (%s).', 'pages'), null, 'add_block.012', '500');
			return;
		}
		if($pre_page['Page']['id'] != $page['Page']['id']) {
			// 移動元表示カウント++(ブロック移動時)
			$this->Page->id = $pre_page['Page']['id'];
			if(!$this->Page->saveField('show_count', intval($pre_page['Page']['show_count']) + 1)) {
				$this->flash(__('Failed to update the database, (%s).', 'pages'), null, 'add_block.013', '500');
				return;
			}
		}

		if(!empty($this->request->params['requested']) && !empty($copy_block_id)) {
			// ペースト OR ショートカット
			$this->autoRender = false;
			return $last_id;
		}

		$params = array('block_id' => $last_id);
		$controller_arr = explode('/', $module['Module']['edit_controller_action'], 2);
		$params['plugin'] = $params['controller'] = $controller_arr[0];
		if(isset($controller_arr[1])) {
			$params['action'] = $controller_arr[1];
		}
		$this->redirect($params);
	}

/**
 * ブロック削除
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function del_block() {
		$block = $this->nc_block;
		$page = $this->nc_page;
		$block_id = $block['Block']['id'];
		$page_id = $page['Page']['id'];
		$show_count = $this->request->data['show_count'];
		$all_delete = $this->request->data['all_delete'];

		if(!$page || $page['Page']['show_count'] != $show_count) {
			$this->flash(__d('block', 'Because of the possibility of inconsistency happening, update will not be executed. <br /> Please redraw and update again.'), null, 'del_block.001', '400');
			return;
		}

		// --------------------------------------
		// --- 前詰め処理(移動元)		      ---
		// --------------------------------------
		$dec_ret = $this->BlockMoveOperation->decrementRowNum($block);
		if(!$dec_ret) {
			$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'del_block.002', '500');
			return;
		}

		$count_row_num = $this->BlockMoveOperation->findRowCount($block['Block']['page_id'], $block['Block']['parent_id'], $block['Block']['col_num']);
		if($count_row_num == 1) {
			//移動前の列が１つしかなかったので
			//列--
			$dec_ret = $this->BlockMoveOperation->decrementColNum($block);
			if(!$dec_ret) {
				$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'del_block.003', '500');
				return;
			}
		}

        // --------------------------------------
		// --- ブロック削除処理     	      ---
		// --------------------------------------
		if(!$this->Block->deleteBlock($block, $all_delete)) {
			$this->flash(__('Failed to delete the database, (%s).', 'blocks'), null, 'del_block.004', '500');
			return;
		}

		//グループ化した空ブロック削除処理
		if($count_row_num == 1) {
			if(!$this->BlockMove->delGroupingBlock($block['Block']['parent_id'])) {
				$this->flash(__('Failed to delete the database, (%s).', 'blocks'), null, 'del_block.005', '500');
				return;
			}
		}

		// 表示カウント++
		$this->Page->id = $page_id;
		if(!$this->Page->saveField('show_count', intval($show_count) + 1)) {
			$this->flash(__('Failed to update the database, (%s).', 'pages'), null, 'del_block.006', '500');
			return;
		}

		echo 'true';
		$this->render(false);
	}

/**
 * ブロック移動 - 行移動
 * ブロック操作 - 移動
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function insert_row() {
		$user_id = $this->Auth->user('id');
		$block = $this->nc_block;
		$page = $this->nc_page;
		$page_id = $page['Page']['id'];
		$show_count = $this->request->data['show_count'];
		$pre_page = $page;

		if(!empty($this->request->params['requested'])) {
			$pre_page = $this->Page->findAuthById(intval($block['Block']['page_id']), $user_id);
			if(!$pre_page || $pre_page['Authority']['hierarchy'] < NC_AUTH_MIN_CHIEF) {
				$this->flash(__('Authority Error!  You do not have the privilege to access this page.'), null, 'insert_row.001', '403');
				return;
			}
			$content = array('Content' => $block['Content']);
			$ret_validator = $this->BlockMove->validatorRequestContent($content, $pre_page, $page);
			if($ret_validator !== true) {
				// error
				$this->flash($ret_validator, null, 'insert_row.002', '400');
				return;
			}
		}

		if(!$this->BlockMove->validatorRequest($this->request)) {
			// Error
			$this->flash(__('Unauthorized request.<br />Please reload the page.'), null, 'insert_row.003', '400');
			return;
		}

		if(!$page || $page['Page']['show_count'] != $show_count) {
			$this->flash(__d('block', 'Because of the possibility of inconsistency happening, update will not be executed. <br /> Please redraw and update again.'), null, 'insert_row.004', '400');
			return;
		}

		$ret = $this->BlockMove->InsertRow($block, $this->request->data['parent_id'], $this->request->data['col_num'], $this->request->data['row_num'], $pre_page, $page);
		if(!$ret) {
			$this->flash(__('The server encountered an internal error and was unable to complete your request.'), null, 'insert_row.005', '500');
			return;
		}

		// 表示カウント++
		$this->Page->id = $page_id;
		if(!$this->Page->saveField('show_count', intval($show_count) + 1)) {
			$this->flash(__('Failed to update the database, (%s).', 'pages'), null, 'insert_row.006', '500');
			return;
		}

		if($pre_page['Page']['id'] != $page['Page']['id']) {
			// 移動元表示カウント++(ブロック移動時)
			$this->Page->id = $pre_page['Page']['id'];
			if(!$this->Page->saveField('show_count', intval($pre_page['Page']['show_count']) + 1)) {
				$this->flash(__('Failed to update the database, (%s).', 'pages'), null, 'insert_row.007', '500');
				return;
			}
		}
		echo 'true';
		$this->render(false);
	}

/**
 * ブロック移動 - 列追加
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function insert_cell() {
		$user_id = $this->Auth->user('id');
		$insert_page = null;
		$block = $this->nc_block;
		$page = $this->nc_page;
		$page_id = $page['Page']['id'];
		$show_count = $this->request->data['show_count'];

		if(!empty($this->request->params['requested']) && isset($this->request->data['page_id'])) {
			$insert_page = $this->Page->findAuthById(intval($this->request->data['page_id']), $user_id);
			if(!$insert_page || $insert_page['Authority']['hierarchy'] < NC_AUTH_MIN_CHIEF) {
				$this->flash(__('Authority Error!  You do not have the privilege to access this page.'), null, 'insert_cell.001', '403');
				return;
			}
		}

		if(!$this->BlockMove->validatorRequest($this->request)) {
			// Error
			$this->flash(__('Unauthorized request.<br />Please reload the page.'), null, 'insert_cell.002', '400');
			return;
		}

		if(!$page || $page['Page']['show_count'] != $show_count) {
			$this->flash(__d('block', 'Because of the possibility of inconsistency happening, update will not be executed. <br /> Please redraw and update again.'), null, 'insert_cell.003', '400');
			return;
		}

		// TODO: ShowCountのチェック(insert_page_id)

		$ret = $this->BlockMove->InsertCell($block,  $this->request->data['parent_id'], $this->request->data['col_num'], $this->request->data['row_num'], $page, $insert_page);
		if(!$ret) {
			$this->flash(__('Unauthorized request.<br />Please reload the page.'), null, 'insert_cell.005', '500');
			return;
		}

		// 表示カウント++
		$this->Page->id = $page_id;
		if(!$this->Page->saveField('show_count', intval($show_count) + 1)) {
			$this->flash(__('Failed to update the database, (%s).', 'pages'), null, 'insert_cell.006', '500');
			return;
		}

		echo 'true';
		$this->render(false);
	}

/**
 * ブロックグループ化
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function add_group() {

		if(!is_array($this->request->data['groups']) || count($this->request->data['groups']) == 0) {
			// Error
			$this->flash(__('Unauthorized request.<br />Please reload the page.'), null, 'add_group.001', '400');
			return;
		}

		$block = $this->nc_block;
		$page = $this->nc_page;
		$page_id = $page['Page']['id'];
		$show_count = $this->request->data['show_count'];

		if(!$page || $page['Page']['show_count'] != $show_count) {
			$this->flash(__d('block', 'Because of the possibility of inconsistency happening, update will not be executed. <br /> Please redraw and update again.'), null, 'add_group.002', '400');
			return;
		}

		$block_arr = $this->request->data['groups'];
// TODO: Validatorとして切り出すほうがよい
		$max_col_num = 1;
		$upd_block_id_arr = array();
		$ret = array();
		$ret_pos = array();
		foreach($block_arr as $block_id) {
			$block_id = intval($block_id);
			if($block_id == 0) {
				continue;
			}

			$group_block = $this->Block->findById($block_id);
			$ret_pos[$group_block['Block']['col_num']][$group_block['Block']['row_num']] = $group_block;
			$max_thread_num = $this->BlockMove->maxThreadNum($group_block);
			if($max_thread_num >= 5) {
				$this->flash(__d('block', 'More than this, can not be grouped complex.'), null, 'add_group.003');
				return;
			}
			if($group_block['Block']['page_id'] != $page_id ||
				(!empty($pre_block) && ($group_block['Block']['page_id'] != $pre_block['Block']['page_id'] ||
					 $group_block['Block']['parent_id'] != $pre_block['Block']['parent_id'] ||
					 in_array($block_id, $upd_block_id_arr)))) {
				// グループ化する基点とpage_id,parent_id相違
				$this->flash(__('Unauthorized request.<br />Please reload the page.'), null, 'add_group.004', '500');
				return;
			}
			$upd_block_id_arr[] = $block_id;
			$pre_block = $group_block;
		}
// Validator End

		// 左上の基点を中心に並び替え
		ksort($ret_pos);
		foreach($ret_pos as $k => $v) {
			ksort($ret_pos[$k]);
		}
		foreach($ret_pos as $k => $v) {
			foreach($v as $k_sub => $v_sub) {
				$ret[] = $v_sub;
			}
		}

		$pos = array();
		$upd_blocks = array();

		// update
		foreach($ret as $key => $group_block) {

			//if(empty($group_block)) {
			//	continue;
			//}
			$block_id = intval($group_block['Block']['id']);
			if($key == 0) {
				// グループ化する基点
				/*
				 * Content Insert
				 */
				$ins_content['Content'] = array(
					'module_id' => 0,
					'is_master' => _ON,
					'title' => __d('block', 'New group'),
					'room_id' => $page['Page']['room_id'],
					'accept_flag' => NC_ACCEPT_FLAG_ON,
					'url' => ''
				);
				$this->Content->create();
				$ins_ret = $this->Content->save($ins_content);
				if(!$ins_ret) {
					$this->flash(__('Failed to register the database, (%s).', 'contents'), null, 'add_group.005', '500');
					return;
				}
				$last_content_id = $this->Content->id;
				if(!$this->Content->saveField('master_id', $last_content_id)) {
					$this->flash(__('Failed to update the database, (%s).', 'contents'), null, 'add_group.006', '500');
					return;
				}

				/*
				 * Block Insert
				 */
				$ins_block['Block'] = $group_block['Block'];
				$ins_block = $this->BlockMoveOperation->defaultBlock($ins_block);
				$ins_block['Block']['content_id'] = $this->Content->id;
				//$ins_block['Block']['title'] = __d('block', 'New group');

				$ins_ret = $this->Block->save($ins_block);
				if(!$ins_ret) {
					$this->flash(__('Failed to register the database, (%s).', 'blocks'), null, 'add_group.007', '500');
					return;
				}
				$last_id = $this->Block->id;
				$ins_ret['Block']['id'] = $last_id;
				//$ins_ret['Block']['parent_id'] = $last_id;
				if($ins_ret['Block']['root_id'] == $block_id) {
					$ins_ret['Block']['root_id'] = $last_id;
				}

				$ins_ret = $this->Block->save($ins_ret);
				if(!$ins_ret) {
					$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'add_group.008', '500');
					return;
				}

				$upd_blocks[$key]['Block'] = $group_block['Block'];
				$upd_blocks[$key]['Block']['col_num'] = 1;
				$upd_blocks[$key]['Block']['row_num'] = 1;
				$pos[0][0] = _ON;
			} else {
				$upd_blocks[$key]['Block'] = $group_block['Block'];

				$col_num = count($pos);
				if($group_block['Block']['col_num'] - ($ins_block['Block']['col_num'] - 1) > $max_col_num) {
					$pos[++$col_num - 1][0] = _ON;
					$max_col_num = $group_block['Block']['col_num'];
				} else {	//if($group_block['Block']['col_num'] > $col_num) {
					$pos[$col_num - 1][] = _ON;
				}
				$upd_blocks[$key]['Block']['col_num'] = $col_num;
				$upd_blocks[$key]['Block']['row_num'] = count($pos[$col_num - 1]);

				//前詰め処理(移動元)
				$dec_ret = $this->BlockMoveOperation->decrementRowNum($group_block);
				if(!$dec_ret) {
					$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'add_group.009', '500');
					return;
				}
				//$dec_row_num--;

				$count_row_num = $this->Block->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						"page_id" => $group_block['Block']['page_id'],
						"parent_id" => $group_block['Block']['parent_id'],
						"col_num" => $group_block['Block']['col_num']
					)
				));

				if($count_row_num == 1) {
					//移動前の列が１つしかなかったので
					//列--
					$dec_ret = $this->BlockMoveOperation->decrementColNum($group_block);
					if(!$dec_ret) {
						$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'add_group.010', '500');
						return;
					}
				}

			}
			$root_id = $upd_blocks[$key]['Block']['root_id'];
			$upd_blocks[$key]['Block']['thread_num'] = ++$upd_blocks[$key]['Block']['thread_num'];
			$upd_blocks[$key]['Block']['parent_id'] = $last_id;
			$upd_blocks[$key]['Block']['root_id'] = $ins_ret['Block']['root_id'];
			//$this->Block->create($upd_blocks[$key - 1]);
			$upd_ret = $this->Block->save($upd_blocks[$key]);
			if(!$upd_ret) {
				$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'add_group.011', '500');
				return;
			}
			//グループ化しているブロックならば,そのグループの子供を求める
	    	if($upd_blocks[$key]['Block']['controller_action'] == "group") {
	    		$block_children =& $this->Block->findByRootId($root_id);
	    		$parent_id_arr = array($upd_blocks[$key]['Block']['id']);
	    		foreach ($block_children as $block_child) {
	    			if(in_array($block_child['Block']['parent_id'], $parent_id_arr)) {
	    				$parent_id_arr[] = $block_child['Block']['id'];
	    			} else {
	    				continue;
	    			}

		    		$block_child['Block']['root_id'] = $ins_ret['Block']['root_id'];
		    		$block_child['Block']['thread_num'] = intval($block_child['Block']['thread_num']) + 1;
		    		$save_ret = $this->Block->save($block_child);
		    		if(!$save_ret) {
						$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'add_group.012', '500');
						return;
					}
	    		}
	    	}
		}

		// 表示カウント++
		$this->Page->id = $page_id;
		if(!$this->Page->saveField('show_count', intval($show_count) + 1)) {
			$this->flash(__('Failed to update the database, (%s).', 'pages'), null, 'add_group.013', '500');
			return;
		}

		$params = array('block_id' => $last_id, 'plugin' => 'group', 'controller' => 'group', 'action' => 'index');
		echo ($this->requestAction($params, array('return')));
		$this->render(false, 'ajax');
	}
/**
 * ブロックグループ化解除
 * @param   void
 * @return  void
 * @since   v 3.0.0.0
 */
	public function cancel_group() {
		if(!is_array($this->request->data['cancel_groups']) || count($this->request->data['cancel_groups']) == 0) {
			// Error
			$this->flash(__('Unauthorized request.<br />Please reload the page.'), null, 'cancel_group.001', '400');
			return;
		}

		$block = $this->nc_block;
		$page = $this->nc_page;
		$page_id = $page['Page']['id'];
		$show_count = $this->request->data['show_count'];

		if(!$page || $page['Page']['show_count'] != $show_count) {
			$this->flash(__d('block', 'Because of the possibility of inconsistency happening, update will not be executed. <br /> Please redraw and update again.'), null, 'add_group.002', '400');
			return;
		}

		$block_arr = $this->request->data['cancel_groups'];

// TODO: Validatorとして切り出すほうがよい
		$ret = array();
		$upd_block_id_arr = array();
		foreach($block_arr as $block_id) {
			$block_id = intval($block_id);
			if($block_id == 0) {
				continue;
			}

			$group_block = $this->Block->findById($block_id);

			if($group_block['Block']['controller_action'] != 'group' || $group_block['Block']['page_id'] != $page_id ||
				(!empty($pre_block) && ($group_block['Block']['page_id'] != $pre_block['Block']['page_id'] ||
					 $group_block['Block']['parent_id'] != $pre_block['Block']['parent_id'] ||
					 in_array($block_id, $upd_block_id_arr)))) {
				// グループ化する基点とpage_id,parent_id相違
				$this->flash(__('Unauthorized request.<br />Please reload the page.'), null, 'cancel_group.003', '400');
				return;
			}
			$upd_block_id_arr[] = $block_id;
			$pre_block = $group_block;
			$ret[$block_id] = $group_block;
		}

// Validator End
		foreach($ret as $block_id => $group_block) {
			//グルーピングブロック削除
    		$this->Block->delete($group_block['Block']['id']);
    		$this->Content->delete($group_block['Block']['content_id']);

    		$params = array(
				'conditions' => array('Block.parent_id =' => $block_id),
				'fields' => array('Block.*'),
				'recursive' =>  -1
			);
			$blocks = $this->Block->find("all", $params);

			$row_count = -1;
	    	$col_count = 0;
	    	if(!empty($blocks)) {
		    	foreach($blocks as $sub_block) {
		    		if($sub_block['Block']['col_num'] == 1) {
		    			$row_count++;
		    		} else if($col_count < $sub_block['Block']['col_num'] - 1) {
		    			$col_count = $sub_block['Block']['col_num'] - 1;
		    		}
		    	}
	    	}

    		//親移動
	    	if($row_count != 0) {
			$inc_ret = $this->BlockMoveOperation->incrementRowNum($group_block, $row_count);
				if(!$inc_ret) {
					$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'cancel_group.004', '500');
					return;
				}
	    	}
			if($col_count != 0) {
				$buf_group_block = $group_block;
				$buf_group_block['Block']['col_num']++;
				$inc_ret = $this->BlockMoveOperation->incrementColNum($buf_group_block, $col_count);
				if(!$inc_ret) {
					$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'cancel_group.005', '500');
					return;
				}
			}

			//
	    	// グルーピング解除処理
	    	//
	    	foreach($blocks as $sub_block) {
	    		if($group_block['Block']['id'] != $group_block['Block']['root_id'])
	    			$root_id = $block['Block']['root_id'];
	    		else
	    			$root_id = $sub_block['Block']['id'];

			    $sub_block['Block']['parent_id'] = $group_block['Block']['parent_id'];
			    $pre_root_id = $sub_block['Block']['root_id'];
			    $sub_block['Block']['root_id'] = $root_id;
			    $sub_block['Block']['thread_num'] = $group_block['Block']['thread_num'];

	    		if($sub_block['Block']['col_num'] == 1) {
	    			$sub_block['Block']['col_num'] = intval($group_block['Block']['col_num']);
			    	$sub_block['Block']['row_num'] = intval($sub_block['Block']['row_num']) + intval($group_block['Block']['row_num']) - 1;
	    		} else {
	    			$sub_block['Block']['col_num'] = intval($sub_block['Block']['col_num']) + intval($group_block['Block']['col_num']) - 1;
	    		}

	    		//$this->Block->create();
	    		$save_ret = $this->Block->save($sub_block);
	    		if(!$save_ret) {
					$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'cancel_group.006', '500');
					return;
				}
				//グループ化しているブロックならば,そのグループの子供を求める
				if($sub_block['Block']['controller_action'] == "group") {
		    		$block_children =& $this->Block->findByRootId($pre_root_id);
		    		$parent_id_arr = array($sub_block['Block']['id']);
		    		foreach ($block_children as $block_child) {
		    			if(in_array($block_child['Block']['parent_id'], $parent_id_arr)) {
		    				$parent_id_arr[] = $block_child['Block']['id'];
		    			} else {
		    				continue;
		    			}

			    		$block_child['Block']['root_id'] = $root_id;
			    		$block_child['Block']['thread_num'] = intval($block_child['Block']['thread_num']) - 1;
			    		//$this->Block->create();
			    		$save_ret = $this->Block->save($block_child);
			    		if(!$save_ret) {
							$this->flash(__('Failed to update the database, (%s).', 'blocks'), null, 'cancel_group.007', '500');
							return;
						}
		    		}
		    	}
	    	}
		}

		// 表示カウント++
		$this->Page->id = $page_id;
		if(!$this->Page->saveField('show_count', intval($show_count) + 1)) {
			$this->flash(__('Failed to update the database, (%s).', 'pages'), null, 'cancel_group.008', '500');
			return;
		}

		echo 'true';
		$this->render(false);
	}
}