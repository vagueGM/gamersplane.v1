<?
	abstract class Character {
		protected $bodyClasses = array();

		protected $userID ;
		protected $characterID;
		protected $label;
		protected $charType = 'PC';
		protected $inLibrary = false;
		protected $game = null;
		protected $name;
		protected $notes;

		protected $linkedTables = array();
		protected $mongoIgnore = array('save' => array('bodyClasses', 'linkedTables', 'mongoIgnore'), 'load' => array('_id', 'system'));
		
		public function __construct($characterID, $userID = null) {
			global $currentUser;

			$this->characterID = intval($characterID);
			if ($userID == null) $this->userID = $currentUser->userID;
			else $this->userID = $userID;

			foreach ($this->bodyClasses as $bodyClass) addBodyClass($bodyClass);
		}

		public function clearVar($var) {
			if (isset($this->$var)) {
				if (is_array($this->$var)) $this->$var = array();
				else $this->$var = null;
			}
		}

		public function getCharacterID() {
			return $this->characterID;
		}

		public function setLabel($label) {
			$this->label = $label;
		}

		public function getLabel() {
			return $this->label;
		}

		public function setCharType($charType) {
			global $charTypes;
			if (in_array($charType, $charTypes)) $this->charType = $charType;
		}

		public function getCharType() {
			return $this->charType;
		}

		public function toggleLibrary() {
			$this->inLibrary = $this->inLibrary?false:true;
		}

		public function getLibraryStatus() {
			return $this->inLibrary;
		}

		public function addToGame($gameID) {
			$this->game = array('gameID' => $gameID, 'approved' => false);
		}

		public function approveToGame() {
			$this->game['approved'] = true;
		}

		public function removeFromGame() {
			$this->game = null;
		}

		public function checkPermissions($userID = null) {
			global $mysql;

			if ($userID == null) $userID = $this->userID;
			else $userID = intval($userID);

			$charCheck = $mysql->query("SELECT c.characterID FROM characters c LEFT JOIN players p ON c.gameID = p.gameID AND p.isGM = 1 WHERE c.characterID = {$this->characterID} AND (c.userID = $userID OR p.userID = $userID)");
			if ($charCheck->rowCount()) return 'edit';

			$libraryCheck = $mysql->query("SELECT inLibrary FROM characterLibrary WHERE characterID = {$this->characterID} AND inLibrary = 1");
			if ($libraryCheck->rowCount()) {
				$charCheck = $mysql->query("SELECT c.characterID FROM characters c INNER JOIN players p ON c.gameID = p.gameID AND p.isGM = 0 WHERE c.characterID = {$this->characterID} AND c.userID != $userID AND p.userID = $userID");
				if ($charCheck->rowCount()) return false;
				else return 'library';
			} else return false;
		}
		
		public function showSheet() {
			require_once(FILEROOT.'/characters/'.$this::SYSTEM.'/sheet.php');
		}
		
		public function showEdit() {
			require_once(FILEROOT.'/characters/'.$this::SYSTEM.'/edit.php');
		}
		
		public function setName($name) {
			$this->name = $name;
		}

		public function getName() {
			return $this->name;
		}

		public function setNotes($notes) {
			$this->notes = $notes;
		}

		public function getNotes() {
			return $this->notes;
		}

		public function getForumTop($postAuthor) {
?>
					<p class="charName"><a href="/characters/<?=$this::SYSTEM?>/<?=$this->characterID?>/" class="username"><?=$this->name?></a></p>
					<p class="posterName"><a href="/user/<?=$postAuthor->userID?>/" class="username"><?=$postAuthor->username?></a></p>
<?
		}

		public function getAvatar() {
			if (file_exists(FILEROOT."/characters/avatars/{$this->characterID}.png")) return "/characters/avatars/{$this->characterID}.png";
			else return false;
		}

		public function save() {
			global $mongo;

			$classVars = get_object_vars($this);
			foreach ($this->mongoIgnore['save'] as $key) unset($classVars[$key]);
			$classVars = array_merge(array('system' => $this::SYSTEM), $classVars);
			$mongo->characters->update(array('characterID' => $this->characterID), array('$set' => $classVars), array('upsert' => true));
			addCharacterHistory($this->characterID, 'charEdited');
		}

		public function load() {
			global $mongo;

			$result = $mongo->characters->findOne(array('characterID' => $this->characterID));
			foreach ($result as $key => $value) {
				if (!in_array($key, $this->mongoIgnore['load'])) $this->$key = $value;
			}
		}

		public function delete() {
			global $currentUser, $mysql, $mongo;

			$mysql->query('DELETE FROM characters WHERE characterID = '.$this->characterID);
			foreach ($this->linkedTables as $table) $mysql->query('DELETE FROM '.$this::SYSTEM.'_'.$table.' WHERE characterID = '.$this->characterID);
			$mongo->characters->remove(array('characterID' => $this->characterID));
			
			addCharacterHistory($this->characterID, 'charDeleted', $currentUser->userID, 'NOW()');
		}
	}
?>