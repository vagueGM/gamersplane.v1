<?
	class CharacterFactory {
		private function __construct() { }

		public static function getCharacter($system) {
			if (file_exists(FILEROOT."/includes/packages/{$system}Character.package.php")) {
				require_once(FILEROOT."/includes/packages/{$system}Character.package.php");
				$classname = $system.'Character';
			} else 
				throw new Exception('Invalid type');
			return new $classname();
		}
	}
?>