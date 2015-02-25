<?
	DEFINE(SYSTEM, $pathOptions[1]);
	$characterID = intval($pathOptions[2]);
	$charPermissions = false;
	if ($systems->getSystemID(SYSTEM)) {
		require_once(FILEROOT.'/includes/packages/'.SYSTEM.'Character.package.php');
		$charClass = SYSTEM.'Character';
		$dispatchInfo['title'] = 'Edit '.$systems->getFullName(SYSTEM).' Character Sheet';
		if ($character = new $charClass($characterID)) {
			$character->load();
			$charPermissions = $character->checkPermissions($currentUser->userID);
		}
	}

	require_once(FILEROOT.'/header.php');
?>
		<h1 class="headerbar">Character Avatar</h1>

		<form method="post" action="/characters/process/avatar/" enctype="multipart/form-data" class="hbMargined" style="height: 257px;">
<?	if (!$charPermissions) { ?>
			<p>Seems like you're trying to change a character that isn't yours!</p>
<?	} ?>
<?
	if ($character->getAvatar()) {
		$imageSize = getimagesize(FILEROOT.'/'.$character->getAvatar(false));
?>
			<img id="avatar" src="<?=$character->getAvatar()?>">
<?	} else { ?>
			<div id="avatar"<?=$character->getAvatar()?'':' class="noAvatar"'?>>
				<p>No Avatar</p>
			</div>
<?	} ?>
<?	if ($character->getAvatar()) { ?>
			<p id="delete" class="alignCenter"><input id="deleteAvatar" type="checkbox" name="delete"> <label for="deleteAvatar">Delete avatar<label></p>
<?	} ?>
			<p class="alignCenter"><input type="file" name="avatar"></p>
			<input type="hidden" name="system" value="<?=SYSTEM?>">
			<input type="hidden" name="characterID" value="<?=$characterID?>">
			<p class="alignCenter"><button type="submit" name="submit" class="fancyButton">Upload</button></p>
		</form>
<?	require_once(FILEROOT.'/footer.php'); ?>