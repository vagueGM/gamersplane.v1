<div class="avatar"><div>
	<span ng-if="char && char.avatar">
		<a ng-if="char.permissions" href="/characters/{{char.system}}/{{char.characterID}}/"><img ng-src="{{char.avatar}}"></a>
		<img ng-if="!char.permissions" ng-src="{{char.avatar}}">
	</span>
	<a href="/user/{{user.userID}}/" class="userAvatar"><img ng-src="{{user.avatar.path}}"></a>
</div></div>