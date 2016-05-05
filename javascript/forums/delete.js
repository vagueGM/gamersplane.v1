controllers.controller('deletePost', ['$scope', '$location', '$timeout', 'CurrentUser', 'ForumsService', function ($scope, $location, $timeout, CurrentUser, ForumsService) {
	$scope.$emit('pageLoading');
	var pathElements = getPathElements();
	$scope.postID = pathElements[2]?parseInt(pathElements[2]):0;
	$scope.deleteType == '';
	$scope.buttonPressed = '';
	CurrentUser.load().then(function (loggedIn) {
		$scope.loggedIn = loggedIn;
		if (!loggedIn) 
			location.href = '/forums/';
		$scope.currentUser = CurrentUser.get();
		$scope.thread = {};
		$scope.post = {};
		ForumsService.getPost($scope.postID, true).then(function (data) {
			$scope.$emit('pageLoading');
			if (data.success) {
				$scope.thread = data.thread;
				$scope.post = data.thread.post;

				$scope.deleteType = data.thread.firstPostID == $scope.postID?'thread':'post';
				if (!(
					data.thread.permissions.moderate || 
					(data.thread.post.authorID == $scope.currentUser.userID && $scope.deleteType == 'post' && data.thread.permissions.deletePost) || 
					(data.thread.post.authorID == $scope.currentUser.userID && $scope.deleteType == 'thread' && data.thread.permissions.deleteThread)
				)) 
					location.href = '/forums/thread/' + data.thread.threadID + '/';
			} else {
				location.href = '/forums/';
			}
		});
	});

	$scope.deletePost = function () {
		$scope.$emit('pageLoading');
		ForumsService.deletePost($scope.postID).then(function (data) {
			$scope.$emit('pageLoading');
			if (data.failed) 
				location.href = '/forums/';
			else if (data.deleteType == 'post') 
				location.href = '/forums/thread/' + data.threadID + '/';
			else if (data.deleteType == 'thread') 
				location.href = '/forums/' + data.forumID + '/';
		});
	};
}]);