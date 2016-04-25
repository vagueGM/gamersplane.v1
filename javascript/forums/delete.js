controllers.controller('deletePost', ['$scope', '$location', '$timeout', 'CurrentUser', 'ForumsService', function ($scope, $location, $timeout, CurrentUser, ForumsService) {
	$scope.$emit('pageLoading');
	var pathElements = getPathElements();
	$scope.itemID = pathElements[2]?parseInt(pathElements[2]):0;
	CurrentUser.load().then(function (loggedIn) {
		$scope.loggedIn = loggedIn;
		if (loggedIn) 
			$scope.currentUser = CurrentUser.get();
		loadThread();
	});
