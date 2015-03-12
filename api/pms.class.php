<?
	class pms {
		function __construct() {
			global $loggedIn, $pathOptions;
			if (!$loggedIn) exit;

			if ($pathOptions[0] == 'list' && in_array($_POST['box'], array('inbox', 'outbox'))) 
				$this->displayBox($_POST['box']);
			elseif ($pathOptions[0] == 'allowed' && intval($_POST['pmID'])) 
				$this->checkAllowed($_POST['pmID']);
			elseif ($pathOptions[0] == 'view' && intval($_POST['pmID'])) 
				$this->displayPM($_POST['pmID']);
			elseif ($pathOptions[0] == 'delete' && intval($_POST['pmID'])) 
				$this->deletePM($_POST['pmID']);
			else 
				displayJSON(array('failed' => true));
		}

		public function displayBox($box) {
			global $mongo, $currentUser;

			if ($box == 'inbox') 
				$search = array('recipients.userID' => $currentUser->userID, 'recipients.deleted' => false);
			else 
				$search = array('sender.userID' => $currentUser->userID);
			$pmsResults = $mongo->pms->find($search)->sort(array('datestamp' => -1));
			$pms = array();
			foreach ($pmsResults as $pm) {
				$pm['read'] = true;
				if ($box == 'inbox') {
					$pm['allowDelete'] = true;
					foreach ($pm['recipients'] as $recipient) 
						if ($recipient['userID'] == $currentUser->userID) 
							$pm['read'] = $recipient['read'];
				} else {
					$pm['allowDelete'] = true;
					foreach ($pm['recipients'] as $recipient) {
						if ($recipient['read']) {
							$pm['allowDelete'] = false;
							break;
						}
					}
				}
				$pms[] = $pm;
			}
			displayJSON(array('box' => $box, 'pms' => $pms));
		}

		public function displayPM($pmID) {
			global $mongo, $currentUser;

			$pmID = intval($pmID);

			$pm = $mongo->pms->findOne(array('pmID' => $pmID, '$or' => array(array('sender.userID' => $currentUser->userID), array('recipients.userID' => $currentUser->userID, 'recipients.deleted' => false))));
			if ($pm === null) displayJSON(array('noPM' => true));
			else {
				$pm['title'] = printReady($pm['title']);
				$pm['message'] = printReady($pm['message']);
				$pm['allowDelete'] = true;
				if ($pm['sender']['userID'] == $currentUser->userID) 
					foreach ($pm['recipients'] as $recipient) 
						if ($recipient['read'] && !$recipient['deleted']) 
							$pm['allowDelete'] = false;
				displayJSON($pm);
			}
		}

		public function checkAllowed($pmID) {
			global $mongo, $currentUser;

			$pmID = intval($pmID);
			$pm = $mongo->pms->findOne(array('pmID' => $pmID, '$or' => array(array('sender.userID' => $currentUser->userID), array('recipient.userID' => $currentUser->userID, 'deleted' => false))));
			displayJSON(array('allowed' => $pm?true:false));
		}

		public function deletePM($pmID) {
			global $mongo, $currentUser;

			$pmID = intval($pmID);
			$pm = $mongo->pms->findOne(array('pmID' => $pmID, '$or' => array(array('sender.userID' => $currentUser->userID), array('recipient.userID' => $currentUser->userID))));
			if ($pm === null) 
				displayJSON(array('success' => true));
			elseif ($pm['sender']['userID'] == $currentUser->userID) {
				$allowDelete = true;
				foreach ($pm['recipients'] as $recipient) 
					if ($recipient['read'] && !$recipient['deleted']) 
						$allowDelete = false;

				if ($allowDelete) 
					$mongo->pms->remove(array('pmID' => $pmID));
			} else 
				$mongo->pms->update(array('pmID' => $pmID, 'recipients.userID' => $currentUser->userID), array('recipients.$.deleted' => true));
		}
	}
?>