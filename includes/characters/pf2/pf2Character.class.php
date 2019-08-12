<?
	class pf2Character extends d20Character {
		#Variables
		const SYSTEM = 'pf2';
		protected $ancestry = '';
		protected $background = '';
		protected $alignment = 'tn';
		protected $languages = '';
		protected $charClass = '';
		protected $speed = 0;
		protected $level = 0;
		protected $heropoints = 0;
		protected $xp = 0;
		protected $bulk = 0;
		protected $hp = ['max' => 0, 'current' => 0, 'temp' => 0, 'dying' => 0, 'wounded' => 0];
		protected $proficiency = ['U' => 0, 'T' => 0, 'E' => 0, 'M' => 0, 'L' => 0];
		protected $ac = ['total' => 0, 'dex' => 0, 'item' => 0, 'proficiency' => 'U', 'penalty' = > 0];
		protected $shield = ['acbonus' => 0, 'hardness' => 0, 'maxhp' => 0, 'currenthp' => 0, 'BT' => 0];
		
		protected $skills = [
			['name' => 'Acrobatics', 'stat' => 'dex', 'armorpenalty' => true, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Arcana', 'stat' => 'int', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Athletics', 'stat' => 'str', 'armorpenalty' => true, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Crafting', 'stat' => 'int', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Deception', 'stat' => 'cha', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Diplomacy', 'stat' => 'cha', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Intimidation', 'stat' => 'cha', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Lore', 'stat' => 'int', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Lore', 'stat' => 'int', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Medicine', 'stat' => 'wis', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Nature', 'stat' => 'wis', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Occultism', 'stat' => 'int', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Performance', 'stat' => 'cha', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Religion', 'stat' => 'wis', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Society', 'stat' => 'int', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Stealth', 'stat' => 'dex', 'armorpenalty' => true, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Survival', 'stat' => 'wis', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
			['name' => 'Thievery', 'stat' => 'dex', 'armorpenalty' => true, 'proficiency' => 'U', 'item' => 0, 'note' => ''],
		];

		#Functions
		public function __construct($characterID = null, $userID = null) {
			unset($this->saves, $this->attackBonus);
			parent::__construct($characterID, $userID);
		}
		public function setAncestry($value) {
			$this->ancestry = sanitizeString($value);
		}
		public function getAncestry() {
			return $this->ancestry;
		}
		public function setBackground($value) {
			$this->background = sanitizeString($value);
		}
		public function getBackground() {
			return $this->background;
		}
		public function setAlignment($value) {
			if (dnd5_consts::getAlignments($value) && $value != null) {
				$this->alignment = $value;
			}
		}
		public function getAlignment() {
			return pf2_consts::getAlignments($this->alignment);
		}
		public function setLanguages($value) {
			$this->languages = sanitizeString($value);
		}
		public function getLanguages() {
			return $this->languages;
		}
		public function setCharClass($value) {
			$this->charclass = sanitizeString($value);
		}
		public function getCharClass() {
			return $this->charclass;
		}
		public function setSpeed($value, $hold = null) {
			$this->speed = (int) $value;
		}
		public function getSpeed($key = null) {
			return $this->speed;
		}	
		public function setLevel($value) {
			$this->level = (int) $value;
		}
		public function getLevel() {
			return $this->level;
		}
		public function setHeroPoints($value) {
			$this->heropoint = (int) $value;
		}
		public function getHeroPoints() {
			return $this->heropoint;
		}
		public function setExperience($value) {
			$this->xp = (int) $value;
		}
		public function getExperience() {
			return $this->xp;
		}
		#getHP and setHP through d20Character.class.php
		public function setProficiency($key, $value) {
			if (array_key_exists($key, $this->proficiency)) {
				$this->proficiency[$key] = (int) $value;
			} else {
				return false;
			}
		}
		public function getProficiency($key = null) {
			$proficiency = (array) $this->proficiency;
			if (array_key_exists($key, $proficiency)) {
				return $proficiency[$key];
			} elseif ($key == null) {
				return $proficiency;
			} else {
				return false;
			}
		}
		public function setAC($key, $value) {
			if (array_key_exists($key, $this->ac)) {
				$this->ac[$key] = (int) $value;
			} else {
				return false;
			}
		}
		
#This section needs to handle the skills 
		/*
			Skills need to be handled kind of like a blend between 5e and Pathfinder. 
			Total score = Related Stat + Proficiency Value (lookup by proficiency key) + Item value - armor penalty value (if the armor penalty checkbox for the skill is true).
		
		*/
		static public function skillEditFormat($key = 1, $skillInfo = null) {
			if ($skillInfo == null) {
				$skillInfo = ['name' => '', 'stat' => 'n/a', 'armorpenalty' => false, 'proficiency' => 'U', 'item' => 0, 'note' => ''];
			}
		/*
			In the edit mode, need to have:
			Skill Name | Related Stat Dropdown | Proficiency Dropdown | Item entry box | Armor Penalty Applicable checkbox. | Delete Marker
		*/
	

		}
		
		/*
			In the display mode, need to show:
			Skill Name | Total Score | Related Stat (Short name) | Proficiency Level Key (U/T/E/M/L)
		*/

#Handler for Inventory Management
		/*
			To get up and running quickly, this section could be handled simply through the existing "Items" text box. Make the creation of the inventory management piece a second wave development.
		*/
		
		/*
			Need an entry function similar to skills. Needs to have:
			Item Name | Quantity | Item Bulk | Light Item Checkbox

			If the Light Item Checkbox is true, then the Item Bulk forces a 0 value.
		*/

		/*
			Need a display function similar to skills. Needs to have:
			Item Name | Quantity | Item Bulk | Light Item Checkbox (uneditable)
		*/

		/*
			Need data fields for Inventory:
			Light Item Count = Sum of Quantity where Light Item = True
			Bulk = (Quantity * Item Bulk) + Floor(Light Item Count / 10)
			Encumbered = STR mod * 5
			Max = STR mod * 10
			Item Notes (use the current "Items" text box.
		*/
		
#Armor Details
		/*
			Fields for Armor: Dex Cap (Can be null), Proficiency Drop down, Item Score, Armor Penalty
			Armor Penalty should be a negative number. If >0, multiply by -1. 
			
			$ac 
			[Dex or  Dex Cap] + Proficiency drop down + Item
			AC = 10 + 
		*/






		public function save($bypass = false) {
			$data = $_POST;

			if (!$bypass) {
				$this->setName($data['name']);
				$this->setRace($data['race']);
				$this->setLevel($data['level']);
				$this->setProficiency('U',0);
				$this->setProficiency('T',$data['level']+2);
				$this->setProficiency('E',$data['level']+4);
				$this->setProficiency('M',$data['level']+6);
				$this->setProficiency('L',$data['level']+8);
			}

			parent::save();
		}		
	}
?>