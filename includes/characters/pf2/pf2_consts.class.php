<?
	class dnd5_consts {
		private static $alignments = [
			'lg' => 'Lawful Good',
			'ng' => 'Neutral Good',
			'cg' => 'Chaotic Good',
			'ln' => 'Lawful Neutral',
			'tn' => 'True Neutral',
			'cn' => 'Chaotic Neutral',
			'le' => 'Lawful Evil',
			'ne' => 'Neutral Evil',
			'ce' => 'Chaotic Evil'
		];
		private static $proficiencies = [
			'U' => 'Untrained',
			'T' => 'Trained',
			'E' => 'Expert',
			'M' => 'Master',
			'L' => 'Legendary'
		];

		public static function getAlignments($alignment = null) {
			if ($alignment == null) {
				return self::$alignments;
			} elseif (array_key_exists($alignment, self::$alignments)) {
				return self::$alignments[$alignment];
			} else {
				return false;
			}
		}
		
		public static function getProficiencies($prof = null) {
			if ($prof == null) {
				return self::$proficiencies;
			} elseif (array_key_exists($prof, self::$proficiencies)) {
				return self::$proficiencies[$prof];
			} else {
				return false;
			}
		}
	}
?>
