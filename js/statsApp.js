angular.module('stat', []).controller('statController', ['$scope', '$http', function($scope, $http) {
    $scope.allSentenceCount = '';
    $scope.analizedSentenceCount = '';
    $scope.scores = {'neg': 0, 'neutral': 0, 'pos': 0};
    $scope.incrementScores = function (toInc) {
        $scope.scores[toInc]++;
    };
    $scope.getGreatest = function(neg, neutral, pos) {
        if (pos >= neg && pos >= neutral) {
            return 'pos';
        }
        if (neutral >= neg && neutral >= pos) {
            return 'neutral';
        }
        return 'neg';
    };
    $scope.countSentimentScores = function (scores) {
        for (sentence in scores) {
            console.log(scores[sentence]);
            $scope.incrementScores($scope.getGreatest(scores[sentence].neg, scores[sentence].neutral, scores[sentence].pos));
        }
    };
    $scope.init = function () {
        var request = $http.post('/szovegbanyaszat/statistics.php', {}).then(
                function (response) {
                    console.log('success');
                    $scope.allSentenceCount = response.data.allSentences.cnt;
                    $scope.analizedSentenceCount = response.data.analizedSentences.cnt;
                    console.log($scope.allSentenceCount);
                },
                function (response) {
                    console.log('error');
                    console.log(response);
                }
        );
    };
    $scope.getSentimentScores = function () {
        var request = $http.post('/szovegbanyaszat/statistics.php', {'requestType' : 'sentimentScores'}).then(
                function (response) {
                    console.log('success');
                    console.log(response);
                    $scope.countSentimentScores(response.data.sentimentScores);
                },
                function (response) {
                    console.log('error');
                    console.log(response);
                }
        );
    }
}]);
