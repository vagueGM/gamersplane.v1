<?
	class ForumPoll {
		protected $threadID;
		protected $question;
		protected $options = array();
		protected $oldOptions = array();
		protected $optionsPerUser = 1;
		protected $pollLength;
		protected $allowRevoting = false;

		public function __construct($threadID = null) {
			if ($threadID == null) 
				return true;

			global $mongo, $currentUser;

			$this->threadID = (int) $threadID;
			$poll = $mongo->polls->findOne(['threadID' => $this->threadID]);
			if ($poll) {
				$this->question = $poll['question'];
				$this->optionsPerUser = $poll['optionsPerUser'];
				$this->pollLength = $poll['pollLength'];
				$this->allowRevoting = $poll['allowRevoting'];
				$this->options = $poll['options'];
				foreach ($this->options as &$option) {
					$numVotes = 0;
					$voted = false;
					foreach ($option['votes'] as $vote) {
						if ($vote['userID'] == null) 
							break;
						if ($vote['userID'] == $currentUser->userID) 
							$voted = true;
						$numVotes++;
					}
					$option = [
						'option' => $option['option'],
						'numVotes' => $numVotes,
						'voted' => $voted
					];
				}
			} else 
				throw new Exception('No poll');
		}

		public function __get($key) {
			if (property_exists($this, $key)) return $this->$key;
		}

		public function setThreadID($value) {
			$this->threadID = intval($value);
		}

		public function setQuestion($value) {
			$this->question = sanitizeString(html_entity_decode($value));
		}

		public function getQuestion($pr = false) {
			if ($pr) return printReady($this->question);
			else return $this->question;
		}

		public function parseOptions($value) {
			if (sizeof($this->options)) $this->oldOptions = $this->options;
			$this->options = array();
			$options = preg_split('/\n/', $value);
			array_walk($options, function (&$value, $key) { $value = sanitizeString($value); });
			foreach ($options as $option) 
				if (strlen($option)) $this->options[] = $option;
		}

		public function getOptions($key = null) {
			if ($key == null) return $this->options;
			elseif (array_key_exists($key, $this->options)) return (object) array_merge(array('pollOptionID' => $key), (array) $this->options[$key]);
			else return null;
		}

		public function setOptionsPerUser($value) {
			$this->optionsPerUser = intval($value);
		}

		public function getOptionsPerUser() {
			return $this->optionsPerUser;
		}

		public function setAllowRevoting($value = null) {
			$this->allowRevoting = $value != null?true:false;
		}

		public function getAllowRevoting() {
			return $this->allowRevoting;
		}

		public function savePoll($threadID = null) {
			global $mysql;

			if (strlen($this->question) == 0 || sizeof($this->options) == 0) return null;

			if ($threadID != null && is_int($threadID)) {
				$this->threadID = intval($threadID);
				if (strlen($this->question) && sizeof($this->options)) {
					$addPollOptions = $mysql->prepare("INSERT INTO forums_pollOptions SET threadID = {$this->threadID}, `option` = :option");
					foreach ($this->options as $option) {
						$addPollOptions->bindValue(':option', $option);
						$addPollOptions->execute();
					}
				}
			} else {
				$options = preg_split('/\n/', $value);
				array_walk($options, function (&$value, $key) { $value = sanitizeString($value); });
				$loadedOptions = array();
				foreach ($this->oldOptions as $pollOptionID => $option) 
					$loadedOptions[] = $option;
				$addPollOption = $mysql->prepare("INSERT INTO forums_pollOptions SET threadID = {$this->threadID}, `option` = :option");
				foreach ($loadedOptions as $option) {
					if (in_array($option, $loadedOptions)) unset($loadedOptions[array_search($option, $loadedOptions)]);
					else {
						$addPollOption->bindValue(':option', $option);
						$addPollOption->execute();
					}
					if (sizeof($loadedOptions)) $mysql->query('DELETE FROM po, pv USING forums_pollOptions po LEFT JOIN forums_pollVotes pv ON po.pollOptionID = pv.pollOptionID WHERE po.pollOptionID IN ('.implode(', ', array_keys($loadedOptions)).')');
				}
			}
			$addPoll = $mysql->prepare("INSERT INTO forums_polls (threadID, poll, optionsPerUser, allowRevoting) VALUES ({$this->threadID}, :poll, :optionsPerUser, :allowRevoting) ON DUPLICATE KEY UPDATE poll = :poll, optionsPerUser = :optionsPerUser, allowRevoting = :allowRevoting");
			$addPoll->bindValue(':poll', $this->question);
			$addPoll->bindValue(':optionsPerUser', $this->optionsPerUser);
			$addPoll->bindValue(':allowRevoting', $this->allowRevoting?1:0);
			$addPoll->execute();
		}

		public function addVotes($votes) {
			global $mysql, $currentUser;

			if ($this->getAllowRevoting()) $this->clearVotesCast();
			$addVote = $mysql->prepare("INSERT INTO forums_pollVotes SET userID = {$currentUser->userID}, pollOptionID = :vote, votedOn = NOW()");
			foreach ($votes as $vote) {
				$addVote->bindParam(':vote', $vote);
				$addVote->execute();
			}
		}

		public function getVotesCast() {
			$cast = 0;
			foreach ($this->options as $option) 
				if ($option['voted']) 
					$cast++;
			return $cast;
		}

		public function clearVotesCast() {
			global $mysql, $currentUser;

			$mysql->query("DELETE v FROM forums_pollVotes v INNER JOIN forums_pollOptions o USING (pollOptionID) WHERE o.threadID = {$this->threadID} AND v.userID = {$currentUser->userID}");
		}

		public function getVoteTotal() {
			$total = 0;
			foreach ($this->options as $option) 
				$total += $option->votes;
			return $total;
		}

		public function getVoteMax() {
			$max = 0;
			foreach ($this->options as $option) 
				if ($option['numVotes'] > $max) 
					$max = $option['numVotes'];
			return $max;
		}

		public function delete() {
			global $mongo;

			return $mongo->polls->remove(['threadID' => $this->threadID]);
		}

		public function getPollVars() {
			$poll = get_object_vars($this);
			$poll['voted'] = false;
			$poll['totalVotes'] = 0;
			$poll['highestVotes'] = 0;
			foreach ($poll['options'] as &$option) {
				$option['option'] = printReady($option['option']);
				if ($option['voted']) 
					$poll['voted'] = true;
				$poll['totalVotes'] += $option['numVotes'];
				if ($option['numVotes'] > $poll['highestVotes']) 
					$poll['highestVotes'] = $option['numVotes'];
			}
			return $poll;
		}
	}
?>