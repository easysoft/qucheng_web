date_time := $(shell date +%Y%m%d )
export commit_id := $(shell git rev-parse --short HEAD)
export branch_name := $(shell git branch --show-current | tr '/' '-' )
export TAG := $(shell echo $(branch_name)-$(date_time)-$(commit_id) )

help: ## this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {sub("\\\\n",sprintf("\n%22c"," "), $$2);printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## 构建镜像
	docker build --build-arg VERSION=$(TAG) \
	--build-arg GIT_COMMIT=$(commit_id) \
	--build-arg GIT_BRANCH=$(branch_name) \
	-t hub.qucheng.com/platform/qucheng:$(TAG) -f docker/Dockerfile .

push: ## push 镜像
	docker push hub.qucheng.com/platform/qucheng:$(TAG)

run: ## 运行
	docker-compose -f docker-compose.yml up -d mysql qucheng

run-dev: ## 运行开发环境
	chown 33:33 . -R
	[ ! -d frontend/www/data ] && mkdir -pv frontend/www/data  && chown 33.33 frontend/www/data 
	docker-compose -f docker-compose.yml up -d mysql qucheng-dev

ps: run ## 运行状态
	docker-compose -f docker-compose.yml ps

stop: ## 停服务
	docker-compose -f docker-compose.yml stop

restart: build clean ps ## 重构

clean: stop ## 停服务
	docker-compose -f docker-compose.yml down
	docker volume prune -f

logs: ## 查看运行日志
	docker-compose -f docker-compose.yml logs
