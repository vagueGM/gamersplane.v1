$(function () {
	$('#forumSub').click(function (e) {
		e.preventDefault();

		$link = $(this);
		$.get($(this).attr('href'), {}, function (data) {
			if ($link.text().substring(0, 3) == 'Uns') 
				$link.text('Subscribe to ' + $link.text().split(' ')[2]);
			else 
				$link.text('Unsubscribe from ' + $link.text().split(' ')[2]);
		});
	});
});

controllers.controller('forum', ['$scope', 'Range', 'CurrentUser', 'ForumsService', function ($scope, Range, CurrentUser, ForumsService) {
	$scope.PAGINATE_PER_PAGE = PAGINATE_PER_PAGE;
	$scope.$emit('pageLoading');
	pathElements = getPathElements();
	$scope.forumID = pathElements[1]?parseInt(pathElements[1]):0;
	$scope.forums = {};
	$scope.mainStructure = {};
	$scope.pagination = { current: parseInt($.urlParam('page')) > 0?parseInt($.urlParam('page')):1 };
	CurrentUser.load().then(function (loggedIn) {
		$scope.loggedIn = loggedIn;
		$scope.currentUser = CurrentUser.get();
		$scope.$emit('pageLoading');
		$scope.forums = {};
		$scope.threads = [];
		$scope.mainStructure = [];
		ForumsService.getForum(pathElements[1], true, $scope.pagination.current).then(function (data) {
			$scope.forums = data.forums;
			$scope.threads = data.threads?data.threads:[];
			$scope.currentForum = $scope.forums[$scope.forumID];
			if (!$scope.currentForum.permissions.read) 
				window.location.href = '/forums/';
			forumSet = [];
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

	$scope.getNumPages = function (count) {
		return Math.ceil(count / PAGINATE_PER_PAGE);
	}

	$scope.paginateThread = function (postCount) {
		numPages = $scope.getNumPages(postCount);
		start = numPages <= 4?1:numPages - 1;
		return Range.get(start, numPages, 1);
	}
}]);