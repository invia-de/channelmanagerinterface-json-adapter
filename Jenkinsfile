pipeline {
    agent none

    stages {
        stage('Checkout') {
            agent any
            steps {
                checkout scm
            }
        }
        stage('Download/Update PHP agent') {
			agent { docker 'ideaplexus/php:7.1-fpm' }
			steps {
			    echo '';
			}
        }
		stage('Install PHP dependencies') {
			agent {
			    docker {
			        image 'ideaplexus/php:7.1-fpm'
			        args '-u 0:0'
			    }
            }
			steps {
                withCredentials([[$class: 'SSHUserPrivateKeyBinding', credentialsId: 'jenkins_ssh_key', keyFileVariable: 'ID_RSA']]) {
                    sh 'mkdir -p /root/.ssh'
                    sh 'cp $ID_RSA /root/.ssh/id_rsa'
                    sh 'echo "Host *" > /root/.ssh/config'
                    sh 'echo "    PasswordAuthentication  no" >> /root/.ssh/config'
                    sh 'echo "    StrictHostKeyChecking   no" >> /root/.ssh/config'
                    sh 'echo "    UserKnownHostsFile      /dev/null" >> /root/.ssh/config'
                }
				sh 'composer install --no-progress --no-interaction --optimize-autoloader'
			}
		}
		stage('Run PHPUnit') {
			agent {
			    docker {
			        image 'ideaplexus/php:7.1-fpm'
			        args '-u 0:0'
			    }
            }
			steps {
                sh 'mkdir -p build/coverage'
                sh 'php vendor/bin/phpunit --log-junit build/junit.xml --coverage-clover build/clover.xml'
			}
		}
		stage('Run SonarQube analysis (master)') {
			agent any
			when{ branch 'master' }
			steps {
				script {
				  scannerHome = tool 'sonar'
				}
				withSonarQubeEnv('sonar') {
				  sh "${scannerHome}/bin/sonar-scanner"
				}
			}
		}
		stage('Run SonarQube analysis (develop)') {
			agent any
			when{ branch 'develop' }
			steps {
				script {
				  scannerHome = tool 'sonar'
				}
				withSonarQubeEnv('sonar') {
				  sh "${scannerHome}/bin/sonar-scanner -Dsonar.projectName='CMI JSON AdapterBundle (develop)' -Dsonar.projectKey='cmi-json-adapterbundle_develop'"
				}
			}
		}
    }
}