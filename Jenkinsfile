node {
    checkout scm

    stage('Download/Update PHP docker image') {
        docker.image('ideaplexus/php:7.2').inside {
            echo '';
        }
    }

    stage('Install PHP dependencies') {
        docker.image('ideaplexus/php:7.2').inside('-u 0:0') {
            withCredentials([[$class: 'SSHUserPrivateKeyBinding', credentialsId: 'jenkins_ssh_key', keyFileVariable: 'ID_RSA']]) {
                sh 'mkdir -p /root/.ssh'
                sh 'cp $ID_RSA /root/.ssh/id_rsa'
                sh 'echo "Host *" > /root/.ssh/config'
                sh 'echo "    PasswordAuthentication  no" >> /root/.ssh/config'
                sh 'echo "    StrictHostKeyChecking   no" >> /root/.ssh/config'
                sh 'echo "    UserKnownHostsFile      /dev/null" >> /root/.ssh/config'
            }
            sh 'composer install --no-progress --no-interaction --optimize-autoloader --no-scripts'
        }
    }

    stage('Run PHPUnit') {
        docker.image('ideaplexus/php:7.2').inside("-u 0:0") {
            sh 'docker-php-ext-enable xdebug'
            sh 'php vendor/bin/phpunit --log-junit build/junit.xml --coverage-clover build/clover.xml'
        }
    }

    stage('Run SonarQube analysis') {
        shortCommit = sh(returnStdout: true, script: "git log -n 1 --pretty=format:'%h'").trim()
        switch (env.BRANCH_NAME) {
            case 'master':
                script {
                    scannerHome = tool 'sonar'
                }
                withSonarQubeEnv('sonar') {
                    sh "${scannerHome}/bin/sonar-scanner -Dsonar.projectVersion='${shortCommit}' -Dsonar.projectName='CMI JSON AdapterBundle' -Dsonar.projectKey='cmi-json-adapterbundle'"
                }
                break
            case 'develop':
                script {
                    scannerHome = tool 'sonar'
                }
                withSonarQubeEnv('sonar') {
                    sh "${scannerHome}/bin/sonar-scanner -Dsonar.projectVersion='${shortCommit}' -Dsonar.projectName='CMI JSON AdapterBundle (develop)' -Dsonar.projectKey='cmi-json-adapterbundle_develop'"
                }
                break
            default:
                script {
                  scannerHome = tool 'sonar'
                }
                withSonarQubeEnv('sonar') {
                  sh "${scannerHome}/bin/sonar-scanner -Dsonar.projectVersion='${shortCommit}' -Dsonar.projectName='CMI JSON AdapterBundle (branch)' -Dsonar.projectKey='cmi-json-adapterbundle_branch'"
                }
        }
    }
}