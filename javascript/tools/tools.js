controllers.controller('tools', ['$scope', '$http', 'CurrentUser', function ($scope, $http, CurrentUser) {
	$scope.$emit('pageLoading');
	CurrentUser.load().then(function () {
		$scope.CurrentUser = CurrentUser.get();
		$scope.$emit('pageLoading');
	});
}]);