<?
	$characterID = intval($pathOptions[1]);
	$talentID = intval($pathOptions[3]);
	if (allowCharEdit($characterID, $currentUser->userID)) {
		$talentInfo = $mysql->query("SELECT tl.name, ct.notes FROM starwarsffg_talents ct INNER JOIN starwarsffg_talentsList tl USING (talentID) WHERE ct.talentID = $talentID AND ct.characterID = $characterID");
		if ($talentInfo->rowCount()) $talentInfo = $talentInfo->fetch();
	} else
		$noChar = true;
?>
<?	require_once(FILEROOT.'/header.php'); ?>
		<h1 class="headerbar"><?=mb_convert_case($talentInfo['name'], MB_CASE_TITLE)?></h1>

<?	if ($noChar) { ?>
		<h2 id="noCharFound">No Character Found</h2>
<?	} elseif (!isset($talentInfo)) { ?>
		<h2 id="noTalent">This character does not have this talent.</h2>
<?	} else { ?>
		<form method="post" action="/characters/process/starwarsffg/editTalentNotes/">
			<input id="characterID" type="hidden" name="characterID" value="<?=$characterID?>">
			<input id="talentID" type="hidden" name="talentID" value="<?=$talentID?>">
			<textarea name="notes"><?=$talentInfo['notes']?></textarea>
			<div id="submitDiv" class="alignCenter">
				<button type="submit" name="save" class="fancyButton">Save</button>
			</div>
		</form>
<?	} ?>
<?	require_once(FILEROOT.'/footer.php'); ?>