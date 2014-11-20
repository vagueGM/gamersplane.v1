<?
	if (isset($_POST['create'])) {
		$systemID = intval($_POST['system']);
		$errors = '?';
		$systemShort = $systems->getShortName($systemID);
		if ($systemShort == FALSE) $errors .= 'invalidType=1&';
		if (strcmp(filterString($_POST['label']), $_POST['label']) || $_POST['label'] == '') $errors .= 'invalidLabel=1&';

		if ($errors != '?') {
			header('Location: /characters/my/'.substr($errors, 0, -1));
		} else {
			$addCharacter = $mysql->prepare('INSERT INTO characters (userID, label, charType, systemID) VALUES (:userID, :label, :charType, :systemID)');
			$addCharacter->bindValue(':userID', $currentUser->userID);
			$addCharacter->bindValue(':label', $_POST['label']);
			$addCharacter->bindValue(':charType', $_POST['charType']);
			$addCharacter->bindValue(':systemID', $systemID);
			$addCharacter->execute();
			$characterID = $mysql->lastInsertId();

			require_once(FILEROOT.'/includes/packages/'.$systemShort.'Character.package.php');

			$charClass = $systemShort.'Character';
			$newChar = new $charClass($characterID);
			$newChar->setLabel($_POST['label']);
			$newChar->setCharType($_POST['charType']);
			$newChar->save();
			addCharacterHistory($characterID, 'charCreated', $currentUser->userID, 'NOW()', $systemID);

			header('Location: /characters/'.$systemShort.'/'.$characterID.'/edit/new/');
		}
	} else {
		header('Location: /403');
	}
?>