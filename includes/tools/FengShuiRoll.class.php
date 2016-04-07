<?
	class FengShuiRoll extends Roll {
		protected $modifier;
		protected $die;

		function __construct() {
			$this->die = new BasicDie(6);
		}

		function newRoll($actionValue, $options = array()) {
			$modifier = $options[0];
			$actionValue = intval($actionValue);
			$this->roll = $actionValue > 0?$actionValue:0;
			$this->modifier = $modifier;
		}

		function roll() {
			$this->rolls = array('p' => array(), 'n' => array(), 'e' => null);
			if ($this->modifier == 'standard' || $this->modifier == 'fortune') {
				do {
					$roll = $this->die->roll();
					$this->rolls['p'][] = $roll;
				} while ($roll == 6);
				do {
					$roll = $this->die->roll();
					$this->rolls['n'][] = $roll;
				} while ($roll == 6);
				if ($this->modifier == 'fortune') 
					$this->rolls['e'] = $this->die->roll();
			} elseif ($this->modifier == 'closed') {
				$this->rolls['p'][] = $this->die->roll();
				$this->rolls['n'][] = $this->die->roll();
			}
		}

		function forumLoad($rollData) {
			$this->rollID = $rollData['rollID'];
			$this->reason = $rollData['reason'];
			$this->rolls = ($rollData['rolls']);
			$this->roll = $rollData['actionValue'];
			$this->modifier = $rollData['modifier'];
			$this->setVisibility($rollData['visibility']);
		}

		function mongoFormat() {
			return [
				'type' => 'fengshui',
				'reason' => $this->reason,
				'rolls' => $this->rolls,
				'actionValue' => $this->roll,
				'modifier' => $this->modifier,
				'visibility' => $this->visibility
			];
		}

		function getResults() {
		}

		function showHTML($showAll = false) {
			if (sizeof($this->rolls)) {
				$sum = 0;
				$hidden = false;
				echo '<div class="roll">';
				echo '<p class="rollString">';
				echo ($showAll && $this->visibility > 0)?'<span class="hidden">'.$this->visText[$this->visibility].'</span> ':'';
				if ($this->visibility <= 2) echo $this->reason;
				elseif ($showAll) { echo '<span class="hidden">'.($this->reason != ''?"{$this->reason}":''); $hidden = true; }
				else echo 'Secret Roll';
				echo $hidden?'</span>':'';
				echo '</p>';
				if ($this->visibility <= 1 || $showAll) {
					echo '<div class="rollResults">';
					echo $this->roll;
					if ($this->modifier != 'closed') {
						echo ' + [ '.implode(', ', $this->rolls['p']).' ]';
						echo ' - [ '.implode(', ', $this->rolls['n']).' ]';
						if ($this->rolls['e']) 
							echo ' + '.$this->rolls['e'];
					} else {
						echo ' + '.$this->rolls['p'][0];
						echo ' - '.$this->rolls['n'][0];
					}
					$sum = $this->roll + array_sum($this->rolls['p']) - array_sum($this->rolls['n']);
					if ($this->modifier == 'fortune') 
						$sum += $this->rolls['e'];
					echo ' = '.$sum;
					echo '</div>';
				}
				echo '</div>';
			}
		}

		public function apiFormat() {
			$roll = $this->mongoFormat();
			$sum = $this->roll + array_sum($this->rolls['p']) - array_sum($this->rolls['n']);
			if ($this->modifier == 'fortune') 
				$sum += $this->rolls['e'];
			$roll['sum'] = $sum;
			return $roll;
		}
	}
?>