<?
	class BasicRoll extends Roll {
		protected $rerollAces = false;

		function __construct() { }

		function newRoll($diceString, $options = array()) {
			if (isset($options['rerollAces']) && $options['rerollAces']) $this->rerollAces = true;
			$this->parseRolls($diceString);
		}

		function parseRolls($diceString) {
			preg_match_all('/(\d*)[dD](\d+)([+-]\d+)?/', $diceString, $rolls, PREG_SET_ORDER);
			if (sizeof($rolls)) {
				foreach ($rolls as $roll) {
					if ($roll[1] == '') {
						$roll[1] = 1;
						$roll[0] = '1'.$roll[0];
					}
					if (!isset($roll[3])) 
						$roll[3] = 0;
					else 
						$roll[3] = intval($roll[3]);

					$this->rolls[] = array('string' => $roll[0], 'number' => $roll[1], 'sides' => $roll[2], 'modifier' => $roll[3], 'indivRolls' => array(), 'result' => 0);
					$this->dice[$roll[2]] = new BasicDie($roll[2]);
				}

				return true;
			} else return false;
		}

		function roll() {
			foreach ($this->rolls as $key => &$roll) {
				for ($count = 0; $count < $roll['number']; $count++) {
					$result = $this->dice[$roll['sides']]->roll();

					if (isset($roll['indivRolls'][$count]) && is_array($roll['indivRolls'][$count])) $roll['indivRolls'][$count][] = $result;
					elseif ($result == $roll['sides'] && $this->rerollAces) $roll['indivRolls'][$count] = array($result);
					else $roll['indivRolls'][$count] = $result;
					$roll['result'] += $result;

					if ($this->rerollAces && $result == $roll['sides']) $count -= 1;
				}
				$roll['result'] += $roll['modifier'];
			}
		}

		function forumLoad($rollData) {
			$this->reason = $rollData['reason'];
			$this->rolls = $rollData['rolls'];
			$this->setVisibility($rollData['visibility']);
			$this->rerollAces = $rollData['rerollAces'];
		}

		function mongoFormat() {
			return [
				'type' => 'basic',
				'reason' => $this->reason,
				'rolls' => $this->rolls,
				'visibility' => $this->visibility,
				'rerollAces' => $this->rerollAces
			];
		}

		function getResults() {
		}

		function showHTML($showAll = false) {
			if (sizeof($this->rolls)) {
				$hidden = false;

				echo '<div class="roll">';
				$rollStrings = $rollValues = array();
				$multipleRolls = sizeof($this->rolls) > 1?true:false;
				foreach ($this->rolls as $count => $roll) {
					$rollStrings[] = $roll['roll'];
					$rollValues[$count] = '<p class="rollResults">'.($this->visibility != 0 && $showAll?'<span class="hidden">':'').($multipleRolls?"{$roll['roll']} - ":'').'( ';
					$rolls = array();
					$results = array();
					foreach ($roll['results'] as $key => $result) {
						if (sizeof($result) > 1) {
							$rolls[$key] = '[ '.implode(', ', $result).' ]';
							$results[$key] = array_sum($result);
						} else {
							$rolls[$key] = $result;
							$results[$key] = $result;
						}
					}
					$rollValues[$count] .= implode(', ', $rolls).' )';
					if ($roll['modifier'] < 0) 
						$rollValues[$count] .= ' - '.abs($roll['modifier']);
					elseif ($roll['modifier'] > 0) 
						$rollValues[$count] .= ' + '.$roll['modifier'];
					$rollValues[$count] .= ' = '.array_sum($results).($this->visibility != 0?'</span>':'').'</p>';
				}
				echo '<p class="rollString">';
				echo ($showAll && $this->visibility > 0)?'<span class="hidden">'.$this->visText[$this->visibility].'</span> ':'';
				if ($this->visibility <= 2) 
					echo $this->reason;
				elseif ($showAll) {
					echo '<span class="hidden">'.($this->reason != ''?"{$this->reason}":'');
					$hidden = true;
				} else 
					echo 'Secret Roll';
				if ($this->visibility > 1 && $showAll && !$hidden) {
					echo '<span class="hidden">';
					$hidden = true;
				}
				if ($this->visibility <= 1 || $showAll) {
					if (strlen($this->reason)) echo ' - (';
					echo implode(', ', $rollStrings);
					if ($this->rerollAces) echo (strlen($this->reason)?', ':'').(strlen($this->reason) == 0?' [ ':'').'RA'.(strlen($this->reason) == 0?' ]':'');
					if (strlen($this->reason)) echo ')';
				}
				echo $hidden?'</span>':'';
				echo '</p>';
				if ($this->visibility == 0 || $showAll) echo implode('', $rollValues);
				echo '</div>';
			}
		}
	}
?>