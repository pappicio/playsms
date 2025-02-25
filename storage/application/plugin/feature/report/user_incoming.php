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

if (!auth_isvalid()) {
	auth_block();
}
;

switch (_OP_) {
	case "user_incoming":
		$search_category = array(
			_('Time') => 'in_datetime',
			_('From') => 'in_sender',
			_('Keyword') => 'in_keyword',
			_('Content') => 'in_message',
			_('Feature') => 'in_feature' 
		);
		$base_url = 'index.php?app=main&inc=feature_report&route=user_incoming&op=user_incoming';
		$search = themes_search($search_category, $base_url);
		$conditions = array(
			'in_uid' => $user_config['uid'],
			'flag_deleted' => 0,
			'in_status' => 1 
		);
		$keywords = $search['dba_keywords'];
		$count = dba_count(_DB_PREF_ . '_tblSMSIncoming', $conditions, $keywords);
		$nav = themes_nav($count, $search['url']);
		$extras = array(
			'AND in_feature' => '!= ""',
			'ORDER BY' => 'in_id DESC',
			'LIMIT' => $nav['limit'],
			'OFFSET' => $nav['offset'] 
		);
		$list = dba_search(_DB_PREF_ . '_tblSMSIncoming', 'in_id, in_sender, in_keyword, in_datetime, in_feature, in_message', $conditions, $keywords, $extras);
		
		$content = _dialog() . "
			<h2 class=page-header-title>" . _('My feature messages') . "</h2>
			<p>" . $search['form'] . "</p>
			<form id=fm_incoming name=fm_incoming action=\"index.php?app=main&inc=feature_report&route=user_incoming&op=actions\" method=POST>
			" . _CSRF_FORM_ . "
			<input type=hidden name=go value=delete>
			<div class=playsms-actions-box>
				<div class=pull-left>
					<a href=\"" . _u('index.php?app=main&inc=feature_report&route=user_incoming&op=actions&go=export') . "\">" . $icon_config['export'] . "</a>
				</div>
				<div class=pull-right>" . _submit(_('Are you sure you want to delete ?'), 'fm_incoming', 'delete') . "</div>
			</div>
			<div class=table-responsive>
			<table class=playsms-table-list>
			<thead>
			<tr>
				<th width=15%>" . _('Date/Time') . "</th>
				<th width=15%>" . _('From') . "</th>
				<th width=15%>" . _('Keyword') . "</th>
				<th width=52%>" . _('Content') . "</th>
				<th width=3% class=\"sorttable_nosort\" nowrap><input type=checkbox onclick=CheckUncheckAll(document.fm_incoming)></th>
			</tr>
			</thead>
			<tbody>";

		if (isset($list) && is_array($list) && count($list) > 0) {
			foreach ( $list as $item ) {
				$item = core_display_data($item);
				$in_id = $item['in_id'];
				$in_sender = $item['in_sender'];
				$current_sender = report_resolve_sender($user_config['uid'], $in_sender);
				$in_keyword = ($item['in_keyword'] ? $item['in_keyword'] : '-');
				$in_datetime = core_display_datetime($item['in_datetime']);
				$in_feature = ($item['in_feature'] ? $item['in_feature'] : '-');
				// $in_status = ($item['in_status'] == 1 ? '<span class=status_handled />' : '<span class=status_unhandled />');
				// $in_status = strtolower($in_status);
				$in_message = trim($item['in_message']);
				$reply = '';
				$forward = '';
				if ($in_message && $in_sender) {
					$reply = _sendsms($in_sender, $in_keyword . ' ' . $in_message);
					$forward = _sendsms('', $in_keyword . ' ' . $in_message, $icon_config['forward']);
				}
				$c_message = "
					<div id=\"user_incoming_msg\">" . $in_message . "</div>
					<div id=\"msg_option\">" . $reply . " " . $forward . "</div>";
				$content .= "
					<tr>
						<td>$in_datetime</td>
						<td>$current_sender</td>
						<td>" . $icon_config['keyword'] . " " . $in_keyword . "<br />" . $icon_config['feature'] . " " . $in_feature . "</td>
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
					'in_uid' => $user_config['uid'],
					'flag_deleted' => 0,
					'in_status' => 1 
				);
				$extras = array(
					'AND in_keyword' => '!= ""' 
				);
				$list = dba_search(_DB_PREF_ . '_tblSMSIncoming', 'in_sender, in_keyword, in_datetime, in_feature, in_message', $conditions, $search['dba_keywords'], $extras);
				$data[0] = array(
					_('Time'),
					_('From'),
					_('Keyword'),
					_('Content'),
					_('Feature') 
				);
				for ($i = 0; $i < count($list); $i++) {
					$j = $i + 1;
					$data[$j] = array(
						core_display_datetime($list[$i]['in_datetime']),
						$list[$i]['in_sender'],
						$list[$i]['in_keyword'],
						$list[$i]['in_message'],
						$list[$i]['in_feature'] 
					);
				}
				$content = core_csv_format($data);
				$fn = 'user_incoming-' . $user_config['username'] . '-' . $core_config['datetime']['now_stamp'] . '.csv';
				core_download($content, $fn, 'text/csv');
				break;
			
			case 'delete':
				if (isset($_POST['itemid'])) {
					foreach ($_POST['itemid'] as $itemid) {
						$up = array(
							'c_timestamp' => time(),
							'flag_deleted' => '1' 
						);
						dba_update(_DB_PREF_ . '_tblSMSIncoming', $up, array(
							'in_uid' => $user_config['uid'],
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
