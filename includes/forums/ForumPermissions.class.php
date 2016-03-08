<?
	class ForumPermissions {
		public static $scale = array('general' => 1, 'group' => 2, 'user' => 4);
		protected $permissions;

		public static function getPermissions($userID, $forumIDs, $types = null, $forumsData = null) {
			global $mongo;

			$userID = intval($userID);
			if (!is_array($forumIDs)) 
				$forumIDs = array($forumIDs);
			$returnCols = array('_id' => false, 'type' => true, 'forumID' => true);
			$allTypes = array('read', 'write', 'editPost', 'deletePost', 'createThread', 'deleteThread', 'addRolls', 'addDraws', 'moderate');
			if ($types == null) 
				$types = $allTypes;
			elseif (is_string($types)) 
				$types = preg_split('/\s*,\s*/', $types);

			foreach ($types as $type) {
				$returnCols[$type] = true;
				$bTemplate[$type] = 0;
				$aTemplate[$type] = 4;
			}
			
			$allForumIDs = $forumIDs;
			$heritages = array();
			if (sizeof($forumsData)) {
				foreach ($allForumIDs as $forumID) {
					$heritages[$forumID] = $forumsData[$forumID]['heritage'];
					$allForumIDs = array_merge($allForumIDs, $heritages[$forumID]);
				}
			} else {
				$forumInfos = $mongo->forums->find(['forumID' => ['$in' => $allForumIDs]], ['forumID' => true, 'heritage' => true]);
				foreach ($forumInfos as $info) {
					$heritages[$info['forumID']] = explode('-', $info['heritage']);
					$allForumIDs = array_merge($allForumIDs, $heritages[$info['forumID']]);
				}
			}
			$allForumIDs = array_unique($allForumIDs);
			sort($allForumIDs);

			if ($userID) {
				$adminForums = array();
				$adminIn = $mongo->forums->find(['admins' => $userID, 'forumID' => ['$in' => $allForumIDs]], ['forumID' => true]);
				foreach ($adminIn as $forum) 
					$adminForums[] = $forum['forumID'];
				$getPermissionsFor = array();
				$superFAdmin = array_search(0, $adminForums) !== false?true:false;
				foreach ($forumIDs as $forumID) {
					if (sizeof(array_intersect($heritages[$forumID], $adminForums)) || $superFAdmin) $permissions[$forumID] = array_merge($aTemplate, array('admin' => 4));
					else 
						$getPermissionsFor[] = $forumID;
				}
				foreach ($getPermissionsFor as $forumID) 
					$getPermissionsFor = array_merge($getPermissionsFor, $heritages[$forumID]);
				$getPermissionsFor = array_unique($getPermissionsFor);
				sort($getPermissionsFor);
			} else 
				$getPermissionsFor = $allForumIDs;

			if (sizeof($getPermissionsFor)) {
				$rGroupMemberships = $mongo->forumGroups->find(array('members' => $userID), array('groupID' => true));
				$groupMemberships = array();
				foreach ($rGroupMemberships as $membership) 
					$groupMemberships[] = $membership['groupID'];
				$rPermissions = $mongo->forumPermissions->find(array('$or' => array(
					array('type' => 'general'),
					array('type' => 'group', 'groupID' => array('$in' => $groupMemberships)),
					array('type' => 'user', 'userID' => $userID)
				)), $returnCols);
				$rawPermissions = array();
				foreach ($rPermissions as $permission) {
					$forumID = $permission['forumID'];
					if (!isset($rawPermissions[$forumID])) 
						$rawPermissions[$forumID] = $bTemplate;
					foreach ($permission as $type => $setAt) {
						$setAt = $setAt * ForumPermissions::$scale[$permission['type']];
						if (!in_array($type, array('type', 'forumID', 'groupID', 'userID')) && abs($setAt) > abs($rawPermissions[$forumID][$type])) 
							$rawPermissions[$forumID][$type] = $setAt;
					}
				}

				foreach ($forumIDs as $forumID) {
					if (isset($rawPermissions[$forumID])) 
						$permissions[$forumID] = $rawPermissions[$forumID];
					elseif (!isset($permissions[$forumID]))
						$permissions[$forumID] = $bTemplate;
					foreach (array_reverse($heritages[$forumID]) as $heritage) {
						if ($heritage == $forumID) 
							continue;
						if (isset($rawPermissions[$heritage])) 
							foreach ($types as $type) 
								if (abs($rawPermissions[$heritage][$type]) > abs($permissions[$forumID][$type])) 
									$permissions[$forumID][$type] = $rawPermissions[$heritage][$type];
					}
				}
			}

			global $loggedIn;
			foreach ($forumIDs as $forumID) {
				foreach ($permissions[$forumID] as $type => $value) {
					if ($value < 1 || (!$loggedIn && $type != 'read')) 
						$permissions[$forumID][$type] = false;
					else 
						$permissions[$forumID][$type] = true;
				}
				if (!isset($permissions[$forumID]['admin']) || $permissions[$forumID]['admin'] != true) 
					$permissions[$forumID]['admin'] = false;
			}

			return $permissions;
		}
	}
?>