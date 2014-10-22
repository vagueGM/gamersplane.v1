<?
	$loggedIn = checkLogin(0);
	$userID = $_SESSION['userID'];

	require_once(FILEROOT.'/header.php');
?>
		<h1 class="headerbar">Notifications</h1>
		<div class="hbMargined">
<?

	$charHistories = $mysql->query("SELECT c.characterID, c.label, c.systemID, h.enactedBy, eu.username eUsername, h.enactedOn, h.action, cu.userID cUserID, cu.username cUsername, g.gameID, g.title, gu.userID gmID, gu.username gmUsername FROM characterHistory h INNER JOIN characters c ON c.characterID = h.characterID INNER JOIN users eu ON eu.userID = h.enactedBy INNER JOIN users cu ON cu.userID = c.userID LEFT JOIN games g ON h.additionalInfo = g.gameID LEFT JOIN users gu ON g.gmID = gu.userID WHERE (c.userID = {$userID} OR eu.userID = {$userID}) ORDER BY enactedOn DESC LIMIT 30");
	$cNotification = $charHistories->fetch();
	$gameHistories = $mysql->query("SELECT g.gameID, g.title, g.systemID, h.enactedBy, h.enactedOn, h.action, u.userID, u.username, au.userID aUserID, au.username aUsername, c.characterID, c.label charLabel, d.deckID, d.label deckLabel FROM gameHistory h INNER JOIN games g ON g.gameID = h.gameID INNER JOIN players p ON p.gameID = g.gameID INNER JOIN users u ON u.userID = h.enactedBy LEFT JOIN users au ON h.affectedType = 'user' && h.affectedID = au.userID LEFT JOIN characters c ON h.affectedType = 'character' && h.affectedID = c.characterID LEFT JOIN decks d ON h.affectedType = 'deck' && h.affectedID = d.deckID WHERE p.userID = {$userID} && p.primaryGM = 1 ORDER BY enactedOn DESC LIMIT 30");
	$gNotification = $gameHistories->fetch();
	if ($cNotification['enactedOn'] > $gNotification['enactedOn']) $lastDate = date('Ymd', strtotime($cNotification['enactedOn']));
	else $lastDate = date('Ymd', strtotime($gNotification['enactedOn']));

	for ($count = 0; $count < 30; $count++) {
		if ($cNotification['enactedOn'] > $gNotification['enactedOn']) {
			$action = $cNotification['action'];
			$timestamp = strtotime($cNotification['enactedOn']);
			if (date('Ymd', $timestamp) != $lastDate) {
				$lastDate = date('Ymd', $timestamp);
				echo "\t\t\t\t<hr>\n";
			}
			$systemInfo = $systems->getSystemInfo($cNotification['systemID']);
?>
				<div class="timestamp"><?=date('M j, Y H:i:s', $timestamp)?> - </div>
<?			if ($action == 'charCreated') { ?>
				<div class="text">You created a new <span class="system"><?=$systemInfo['fullName']?></span> character: <a href="/characters/<?=$systemInfo['shortName']?>/<?=$cNotification['characterID']?>/"><?=$cNotification['label']?></a></div>
<?			} elseif ($action == 'basicEdited') { ?>
				<div class="text">You edited the basic info for your <span class="system"><?=$systemInfo['fullName']?></span> character: <a href="/characters/<?=$systemInfo['shortName']?>/<?=$cNotification['characterID']?>/"><?=$cNotification['label']?></a></div>
<?			} elseif ($action == 'charEdited') { ?>
				<div class="text"><?=$cNotification['enactedBy'] == $userID?'You':"<a href=\"/ucp/{$cNotification['userID']}/\" class=\"username\">{$cNotification['eUsername']}</a>"?> edited <?=$cNotification['cUserID'] == $userID?'your':"<a href=\"/ucp/{$cNotification['cUserID']}/\" class=\"username\">{$cNotification['cUsername']}</a>'s"?> <span class="system"><?=$systemInfo['fullName']?></span> character: <a href="/characters/<?=$systemInfo['shortName']?>/<?=$cNotification['characterID']?>/"><?=$cNotification['label']?></a></div>
<?			} elseif ($action == 'charDeleted') { ?>
				<div class="text">You deleted your <span class="system"><?=$systemInfo['fullName']?></span> character: <a href="/characters/<?=$systemInfo['shortName']?>/<?=$cNotification['characterID']?>/"><?=$cNotification['label']?></a></div>
<?			} elseif ($action == 'addToLibrary' || $action == 'removeFromLibrary') { ?>
				<div class="text">You <?=$action == 'addToLibrary'?'added':'removed'?> <a href="/characters/<?=$systemInfo['shortName']?>/<?=$cNotification['characterID']?>/"><?=$cNotification['label']?></a> (<span class="system"><?=$systemInfo['fullName']?></span>) <?=$action == 'addToLibrary'?'to':'from'?> the character library</div>
<?			} elseif ($action == 'charFavorited' || $action == 'charUnfavorited') { ?>
				<div class="text"><?=$cNotification['enactedBy'] == $userID?'You':"<a href=\"/ucp/{$cNotification['userID']}/\" class=\"username\">{$cNotification['username']}</a>"?> <?=$action == 'unfavorited'?'un':''?>favorited <?=$cNotification['cUserID'] == $userID?'your':"<a href=\"/ucp/{$cNotification['userID']}/\" class=\"username\">{$cNotification['username']}</a>'s"?> <a href="/characters/<?=$systemInfo['shortName']?>/<?=$cNotification['characterID']?>/"><?=$cNotification['label']?></a> (<span class="system"><?=$systemInfo['fullName']?></span>)</div>
<?			} elseif ($action == 'charApplied') { ?>
				<div class="text">You applied <a href="/characters/<?=$systemInfo['shortName']?>/<?=$cNotification['characterID']?>/"><?=$cNotification['label']?></a> (<span class="system"><?=$systemInfo['fullName']?></span>) to <a href="/ucp/<?=$cNotification['gmID']?>/" class="username"><?=$cNotification['gmUsername']?></a>'s game: <a href="/games/<?=$cNotification['gameID']?>?>/"><?=$cNotification['title']?></a></div>
<?			} elseif ($action == 'characterApproved' || $action == 'characterRejected' || $action == 'characterRemoved') { ?>
				<div class="text"><?=$cNotification['enactedBy'] == $userID?'You':"<a href=\"/ucp/{$cNotification['userID']}/\" class=\"username\">{$cNotification['username']}</a>"?> <?=substr($action, 9)?> <a href="/characters/<?=$systemInfo['shortName']?>/<?=$cNotification['characterID']?>/"><?=$cNotification['label']?></a> (<span class="system"><?=$systemInfo['fullName']?></span>) <?=substr($action, 9) == 'Approved'?'to':'from'?> <?=$cNotification['gmID'] == $userID?'your':"<a href=\"/ucp/{$cNotification['gmID']}/\" class=\"username\">{$cNotification['gmUsername']}</a>"?>'s game: <a href="/games/<?=$cNotification['gameID']?>?>/"><?=$cNotification['title']?></a></div>
<?
			} else $count--;
			$cNotification = $charHistories->fetch();
		} else {
			$action = $gNotification['action'];
			$timestamp = strtotime($gNotification['enactedOn']);
			if (date('Ymd', $timestamp) != $lastDate) {
				$lastDate = date('Ymd', $timestamp);
				echo "\t\t\t\t<hr>\n";
			}
			$systemInfo = $systems->getSystemInfo($gNotification['systemID']);
?>
				<div class="timestamp"><?=date('M j, Y H:i:s', $timestamp)?> - </div>
<?			if ($action == 'newGame') { ?>
				<div class="text">You created a new <span class="system"><?=$systemInfo['fullName']?></span> game: <a href="/games/<?=$gNotification['gameID']?>?>/"><?=$gNotification['title']?></a></div>
<?			} elseif ($action == 'editedGame') { ?>
				<div class="text">You edited your <span class="system"><?=$systemInfo['fullName']?></span> game: <a href="/games/<?=$gNotification['gameID']?>?>/"><?=$gNotification['title']?></a></div>
<?			} elseif ($action == 'playerApplied') { ?>
				<div class="text"><?=$gNotification['enactedBy'] == $userID?'You':"<a href=\"/ucp/{$cNotification['userID']}/\" class=\"username\">{$cNotification['username']}</a>"?> applied to <span class="system"><?=$systemInfo['fullName']?></span> game: <a href="/games/<?=$gNotification['gameID']?>?>/"><?=$gNotification['title']?></a></div>
<?
			} else $count--;
			$gNotification = $gameHistories->fetch();
		}
?>
			<div class="notification">
			</div>
<?	} ?>
		</div>
<? require_once(FILEROOT.'/footer.php'); ?>