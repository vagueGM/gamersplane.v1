<?
	class Deck {
		private $deckID;
		private $label;
		private $type;
		private $deck;
		private $position = 0;
		private $lastShuffle;
		private $permissions = [];

		public function __construct($deckInfo) {
			if (!is_array($deckInfo))
				return;

			$this->deckID = (int) $deckInfo['deckID'];
			$this->label = $deckInfo['label'];
			require_once(FILEROOT.'/includes/DeckTypes.class.php');
			$type = DeckTypes::getInstance()->getDeck($deckInfo['type']);
			if ($type !== false)
				$this->type = $deckInfo['type'];
			$this->deck = $deckInfo['deck'];
			$this->position = (int) $deckInfo['position'];
			$this->lastShuffle = $deckInfo['lastShuffle']->sec;
			$this->permissions = $deckInfo['permissions'];
		}

		public function setDeckID($deckID) {
			$this->deckID = (int) $deckID;
		}

		public function getDeckID() {
			return $this->deckID;
		}

		public function setLabel($label) {
			$this->label = $label;
		}

		public function getLabel() {
			return $this->label;
		}

		public function setType($type) {
			$type = DeckTypes::getInstance()->getDeck($deckInfo['type']);
			if ($type !== false)
				$this->type = $deckInfo['type'];
		}

		public function getType() {
			return $this->type;
		}

		public function setDeck($deck) {
			$this->deck = $deck;
		}

		public function getDeck() {
			return $this->deck;
		}

		public function setPosition($position) {
			$this->position = (int) $position;
		}

		public function getPosition() {
			return $this->position;
		}

		public function setLastShuffle($lastShuffle) {
			$this->lastShuffle = (int) $lastShuffle;
		}

		public function getLastShuffle($format = '') {
			return $format == ''?$this->lastShuffle:date($format, $this->lastShuffle);
		}

		public function setPermissions($permissions) {
			$this->permissions = $permissions;
		}

		public function addPermission($userID) {
			$userID = (int) $userID;
			$this->permissions[] = $addPermission;
			$this->permissions = array_unique($this->permissions);

			return $this->permissions;
		}

		public function removePermission($userID) {
			$userID = (int) $userID;
			$this->permissions = array_diff($this->permissions, [$userID]);

			return $this->permissions;
		}

		public function getPermissions() {
			return $this->permissions;
		}

		public function checkPermission($userID) {
			$userID = (int) $userID;
			return in_array($userID, $this->permissions);
		}
	}
?>
