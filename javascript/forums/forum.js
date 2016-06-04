controllers.controller('forum', ['$scope', 'Range', 'CurrentUser', 'ForumsService', function ($scope, Range, CurrentUser, ForumsService) {
	$scope.PAGINATE_PER_PAGE = PAGINATE_PER_PAGE;
	$scope.$emit('pageLoading');
	pathElements = getPathElements();
	$scope.forumID = pathElements[1]?parseInt(pathElements[1]):0;
	$scope.forums = {};
	$scope.currentForum = {};
	$scope.threads = {};
	$scope.breadcrumbs = [];
	$scope.mainStructure = {};
	$scope.pagination = { current: parseInt($.urlParam('page')) > 0?parseInt($.urlParam('page')):1 };
	CurrentUser.load().then(function (loggedIn) {
		$scope.loggedIn = loggedIn;
		$scope.currentUser = CurrentUser.get();
		$scope.forums = {};
		$scope.threads = [];
		$scope.mainStructure = [];
		ForumsService.getForum($scope.forumID, true, $scope.pagination.current).then(function (data) {
			$scope.$emit('pageLoading');
			$scope.forums = data.forums;
			if (!($scope.forumID in $scope.forums) || !('permissions' in $scope.forums[$scope.forumID]) || !$scope.forums[$scope.forumID].permissions.read)
				window.location.href = '/forums/';
			$scope.currentForum = $scope.forums[$scope.forumID];
			$scope.threads = data.threads?data.threads:[];
			forumSet = [];
			$scope.breadcrumbs = [];
			$scope.currentForum.heritage.forEach(function (forumID) {
				$scope.breadcrumbs.push({ forumID: forumID, title: $scope.forums[forumID].title });
			});
			for (key in $scope.currentForum.children) {
				childID = $scope.currentForum.children[key];
				if ($scope.forums[childID].type == 'c') {
					if (forumSet.length) {
						$scope.mainStructure.push({ 'forumID': null, 'forums': forumSet });
						forumSet = [];
					}
					$scope.mainStructure.push({ 'forumID': childID, 'forums': $scope.forums[childID].children});
					for (sKey in $scope.forums[childID].children)
						$scope.forums[$scope.forums[childID].children[sKey]].latestPost.datePosted = moment.utc($scope.forums[$scope.forums[childID].children[sKey]].latestPost.datePosted * 1000).local().format('MMMM D, YYYY h:mm a');
				} else {
					$scope.forums[childID].latestPost.datePosted = moment.utc($scope.forums[childID].latestPost.datePosted * 1000).local().format('MMMM D, YYYY h:mm a');
					forumSet.push(childID);
				}
			}
			if (forumSet.length) {
				$scope.mainStructure.push({ 'forumID': null, 'forums': forumSet });
				forumSet = [];
			}
		});
	});

	$scope.getThreads = function () {
		ForumsService.getThreads($scope.forumID, $scope.pagination.current).then(function (data) {
			$scope.threads = data.threads?data.threads:[];
		});
	}

	$scope.markAsRead = function () {
		ForumsService.markAsRead($scope.forumID).then(function () {
			$scope.getThreads();
			for (forumID in $scope.forums)
				$scope.forums[forumID].newPosts = false;
		});
	}

	$scope.toggleSub = function () {
		ForumsService.toggleSub('f', $scope.forumID).then(function (data) {
			if (data.success)
				$scope.currentForum.subscribed = !$scope.currentForum.subscribed;
		});
	}

	$scope.getNumPages = function (count) {
		return Math.ceil(count / PAGINATE_PER_PAGE);
	}

	$scope.paginateThread = function (postCount) {
		numPages = $scope.getNumPages(postCount);
		start = numPages <= 4?1:numPages - 1;
		return Range.get(start, numPages, 1);
	}
}]);
