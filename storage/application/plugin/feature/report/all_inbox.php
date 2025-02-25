<?php

/**
 * This file is part of playSMS.
 *
 * playSMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * playSMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_SECURE_') or die('Forbidden');

if (!auth_isadmin()) {
	auth_block();
}

switch (_OP_) {
	case "all_inbox":
		$search_category = array(
			_('User') => 'username',
			_('Time') => 'in_datetime',
			_('From') => 'in_sender',
			_('Message') => 'in_msg' 
		);
		$base_url = 'index.php?app=main&inc=feature_report&route=all_inbox&op=all_inbox';
		$search = themes_search($search_category, $base_url);
		$conditions = array(
			'A.flag_deleted' => 0,
		);
		$keywords = $search['dba_keywords'];
		$join = "INNER JOIN " . _DB_PREF_ . "_tblUser AS B ON A.in_uid=B.uid AND A.flag_deleted=B.flag_deleted";
		$count = dba_count(_DB_PREF_ . '_tblSMSInbox AS A', $conditions, $keywords, '', $join);
		$nav = themes_nav($count, $search['url']);
		$extras = array(
			'ORDER BY' => 'A.in_id DESC',
			'LIMIT' => $nav['limit'],
			'OFFSET' => $nav['offset'] 
		);
		$list = dba_search(_DB_PREF_ . '_tblSMSInbox AS A', 'B.username, A.in_id, A.in_uid, A.in_datetime, A.in_sender, A.in_msg', $conditions, $keywords, $extras, $join);
		
		$content = _dialog() . "
			<h2 class=page-header-title>" . _('All inbox') . "</h2>
			<p>" . $search['form'] . "</p>
			<form id=fm_all_inbox name=fm_all_inbox action=\"index.php?app=main&inc=feature_report&route=all_inbox&op=actions\" method=POST>
			" . _CSRF_FORM_ . "
			<input type=hidden name=go value=delete>
			<div class=playsms-actions-box>
				<div class=pull-left>
					<a href=\"" . _u('index.php?app=main&inc=feature_report&route=all_inbox&op=actions&go=export') . "\">" . $icon_config['export'] . "</a>
				</div>
				<div class=pull-right>" . _submit(_('Are you sure you want to delete ?'), 'fm_all_inbox', 'delete') . "</div>
			</div>
			<div class=table-responsive>
			<table class=playsms-table-list>
			<thead>
			<tr>
				<th width=10%>" . _('User') . "</th>
				<th width=15%>" . _('Date/Time') . "</th>
				<th width=15%>" . _('From') . "</th>
				<th width=57%>" . _('Message') . "</th>
				<th width=3% class=\"sorttable_nosort\" nowrap><input type=checkbox onclick=CheckUncheckAll(document.fm_all_inbox)></th>
			</tr>
			</thead>
			<tbody>";

		if (isset($list) && is_array($list) && count($list) > 0) {
			foreach ( $list as $item ) {
				$item = core_display_data($item);
				$in_username = $item['username'];
				$in_id = $item['in_id'];
				$in_uid = $item['in_uid'];
				$in_sender = $item['in_sender'];
				$current_sender = report_resolve_sender($in_uid, $in_sender);
				$in_datetime = core_display_datetime($item['in_datetime']);
				$in_msg = $item['in_msg'];
				$reply = '';
				$forward = '';
				if ($in_msg && $in_sender) {
					$reply = _sendsms($in_sender, $in_msg);
					$forward = _sendsms('', $in_msg, $icon_config['forward']);
				}
				$c_message = "
					<div id=\"all_inbox_msg\">" . $in_msg . "</div>
					<div id=\"msg_option\">" . $reply . $forward . "</div>";
				$content .= "
					<tr>
						<td>$in_username</td>
						<td>$in_datetime</td>
						<td>$current_sender</td>
						<td>$c_message</td>
						<td nowrap>
							<input type=checkbox name=itemid[] value=\"$in_id\">
						</td>
					</tr>";
			}
		}

		$content .= "
			</tbody>
			</table>
			</div>
			<div class=pull-right>" . $nav['form'] . "</div>
			</form>";
		
		_p($content);
		break;
	
	case "actions":
		$nav = themes_nav_session();
		$search = themes_search_session();
		$go = $_REQUEST['go'];
		switch ($go) {
			case 'export':
				$conditions = array(
					'A.flag_deleted' => 0,
				);
				$join = "INNER JOIN " . _DB_PREF_ . "_tblUser AS B ON A.in_uid=B.uid AND A.flag_deleted=B.flag_deleted";
				$list = dba_search(_DB_PREF_ . '_tblSMSInbox as A', 'B.username, A.in_datetime, A.in_sender, A.in_msg', $conditions, $search['dba_keywords'], '', $join);
				$data[0] = array(
					_('User'),
					_('Time'),
					_('From'),
					_('Message') 
				);
				for ($i = 0; $i < count($list); $i++) {
					$j = $i + 1;
					$data[$j] = array(
						$list[$i]['username'],
						core_display_datetime($list[$i]['in_datetime']),
						$list[$i]['in_sender'],
						$list[$i]['in_msg'] 
					);
				}
				$content = core_csv_format($data);
				$fn = 'all_inbox-' . $core_config['datetime']['now_stamp'] . '.csv';
				core_download($content, $fn, 'text/csv');
				break;
			
			case 'delete':
				if (isset($_POST['itemid'])) {
					foreach ($_POST['itemid'] as $itemid) {
						$up = array(
							'c_timestamp' => time(),
							'flag_deleted' => '1' 
						);
						dba_update(_DB_PREF_ . '_tblSMSInbox', $up, array(
							'in_id' => $itemid 
						));
					}
				}
				$ref = $nav['url'] . '&search_keyword=' . $search['keyword'] . '&page=' . $nav['page'] . '&nav=' . $nav['nav'];
				$_SESSION['dialog']['info'][] = _('Selected incoming message has been deleted');
				header("Location: " . _u($ref));
				exit();
		}
		break;
}
