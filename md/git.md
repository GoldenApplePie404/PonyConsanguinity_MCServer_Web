# Git和GitHub完整开发流程指南

## 📚 目录

1. [从零开始的项目创建流程](#1-从零开始的项目创建流程)
2. [日常开发流程](#2-日常开发流程)
3. [分支管理](#3-分支管理)
4. [备份和版本控制](#4-备份和版本控制)
5. [多开发者协作](#5-多开发者协作)
6. [常用Git命令速查表](#6-常用git命令速查表)
7. [最佳实践](#7-最佳实践)
8. [常见问题解决](#8-常见问题解决)
9. [GitHub功能使用](#9-github功能使用)
10. [安全建议](#10-安全建议)
11. [总结](#11-总结)

## 1. 从零开始的项目创建流程

### 1.1 创建GitHub仓库
1. **登录GitHub**：访问 https://github.com/ 并登录你的账号
2. **创建新仓库**：
   - 点击右上角的 "+" 按钮
   - 选择 "New repository"
   - 填写仓库名称（例如：PonyConsanguinity_MCServer_Web）
   - 添加描述（例如：万驹同源Minecraft服务器网站）
   - 选择公开或私有
   - 可以选择添加README、.gitignore、许可证
   - 点击 "Create repository"

### 1.2 本地项目初始化
```bash
# 进入项目目录
cd c:\Users\czhdq\Desktop\PC_Web

# 初始化Git仓库
git init

# 添加所有文件到暂存区
git add .

# 创建首次提交
git commit -m "初始提交：万驹同源服务器网站项目"

# 添加远程仓库
git remote add origin https://github.com/GoldenApplePie404/PonyConsanguinity_MCServer_Web.git

# 推送到GitHub（首次推送）
git push -u origin main
```

## 2. 日常开发流程

### 2.1 修改代码后的提交流程
```bash
# 查看当前状态
git status

# 查看具体修改内容
git diff

# 添加修改的文件（全部文件）
git add .

# 或者添加特定文件
git add 文件名

# 创建提交
git commit -m "描述你的修改内容"

# 推送到GitHub
git push origin main
```

### 2.2 查看提交历史
```bash
# 查看提交历史（简洁版）
git log --oneline

# 查看提交历史（详细版）
git log

# 查看特定文件的修改历史
git log 文件名

# 查看某次提交的详细内容
git show 提交ID
```

## 3. 分支管理

### 3.1 创建和切换分支
```bash
# 创建新分支
git branch 分支名

# 切换到分支
git checkout 分支名

# 创建并切换到新分支（推荐）
git checkout -b 分支名

# 查看所有分支
git branch

# 查看所有分支（包括远程分支）
git branch -a
```

### 3.2 合并分支
```bash
# 切换到主分支
git checkout main

# 合并指定分支
git merge 分支名

# 删除已合并的分支
git branch -d 分支名

# 强制删除分支
git branch -D 分支名
```

## 4. 备份和版本控制

### 4.1 定期备份
```bash
# 每天工作结束前执行
git add .
git commit -m "每日备份：日期"
git push origin main
```

### 4.2 创建版本标签
```bash
# 创建标签
git tag v1.0.0

# 推送标签到GitHub
git push origin v1.0.0

# 推送所有标签
git push origin --tags

# 查看所有标签
git tag
```

### 4.3 回退到之前的版本
```bash
# 查看提交历史
git log --oneline

# 回退到指定提交（保留修改）
git reset --soft 提交ID

# 回退到指定提交（不保留修改）
git reset --hard 提交ID

# 回退到指定提交（保留修改但暂存）
git reset --mixed 提交ID
```

## 5. 多开发者协作

### 5.1 克隆项目
```bash
# 克隆远程仓库到本地
git clone https://github.com/GoldenApplePie404/PonyConsanguinity_MCServer_Web.git

# 克隆到指定目录
git clone https://github.com/GoldenApplePie404/PonyConsanguinity_MCServer_Web.git 项目目录名
```

### 5.2 拉取最新代码
```bash
# 拉取远程最新代码并合并
git pull origin main

# 拉取远程最新代码但不合并
git fetch origin

# 查看远程更新内容
git diff origin/main
```

### 5.3 解决合并冲突
```bash
# 当出现冲突时，Git会标记冲突文件
# 编辑冲突文件，解决冲突

# 标记冲突已解决
git add 冲突文件

# 完成合并
git commit -m "解决合并冲突"
```

## 6. 常用Git命令速查表

### 6.1 基础命令
```bash
git init                    # 初始化仓库
git clone [url]             # 克隆仓库
git status                  # 查看状态
git add [file]              # 添加文件到暂存区
git commit -m "message"     # 提交修改
git push                    # 推送到远程
git pull                    # 拉取远程更新
```

### 6.2 分支命令
```bash
git branch                  # 查看分支
git branch [name]           # 创建分支
git checkout [name]         # 切换分支
git checkout -b [name]      # 创建并切换分支
git merge [name]            # 合并分支
git branch -d [name]        # 删除分支
```

### 6.3 查看命令
```bash
git log                     # 查看提交历史
git log --oneline           # 查看简洁提交历史
git diff                    # 查看未暂存的修改
git diff --staged           # 查看已暂存的修改
git show [commit]           # 查看提交详情
```

### 6.4 撤销命令
```bash
git checkout -- [file]      # 撤销文件修改
git reset HEAD [file]       # 取消暂存
git reset --hard [commit]   # 回退到指定提交
git revert [commit]         # 撤销指定提交
```

## 7. 最佳实践

### 7.1 提交信息规范
```
格式：<类型>(<范围>): <描述>

类型：
- feat: 新功能
- fix: 修复bug
- docs: 文档更新
- style: 代码格式调整
- refactor: 重构
- test: 测试相关
- chore: 构建/工具相关

示例：
feat(充值): 添加爱发电支付功能
fix(登录): 修复用户登录失败问题
docs(readme): 更新项目说明文档
```

### 7.2 分支命名规范
```
main/master        # 主分支，生产环境代码
develop            # 开发分支
feature/功能名     # 功能开发分支
bugfix/问题描述    # bug修复分支
hotfix/紧急修复    # 紧急修复分支
release/版本号     # 发布分支
```

### 7.3 工作流程建议
```
1. 从main分支创建功能分支
   git checkout -b feature/新功能

2. 在功能分支上开发
   git add .
   git commit -m "feat: 添加新功能"

3. 完成开发后合并到main分支
   git checkout main
   git merge feature/新功能

4. 推送到GitHub
   git push origin main

5. 删除功能分支
   git branch -d feature/新功能
```

## 8. 常见问题解决

### 8.1 推送被拒绝
```bash
# 错误：Updates were rejected because the remote contains work that you do not have locally
# 解决：先拉取远程更新
git pull origin main
# 然后再推送
git push origin main
```

### 8.2 合并冲突
```bash
# 1. 查看冲突文件
git status

# 2. 编辑冲突文件，解决冲突
# 冲突标记：
# <<<<<<< HEAD
# 本地代码
# =======
# 远程代码
# >>>>>>> origin/main

# 3. 解决冲突后添加文件
git add 冲突文件

# 4. 完成合并
git commit -m "解决合并冲突"
```

### 8.3 忘记提交某些文件
```bash
# 修改最后一次提交
git add 忘记的文件
git commit --amend --no-edit
```

### 8.4 查看远程仓库信息
```bash
# 查看远程仓库
git remote -v

# 添加远程仓库
git remote add origin [url]

# 修改远程仓库地址
git remote set-url origin [url]

# 删除远程仓库
git remote remove origin
```

## 9. GitHub功能使用

### 9.1 Issues（问题跟踪）
- 用于报告bug、提出功能请求、讨论问题
- 可以分配给特定开发者
- 可以设置标签、里程碑
- 可以关联到具体的提交

### 9.2 Pull Requests（代码审查）
- 用于代码审查和合并
- 可以进行代码讨论
- 可以要求修改后才能合并
- 可以自动运行CI/CD测试

### 9.3 Actions（自动化）
- 自动化测试、构建、部署
- 可以在特定事件触发
- 可以创建自定义工作流

### 9.4 Wiki（文档）
- 项目文档编写
- 支持Markdown格式
- 可以创建多个页面

### 9.5 Releases（版本发布）
- 发布软件版本
- 附加发布说明
- 上传二进制文件
- 创建版本标签

## 10. 安全建议

### 10.1 保护敏感信息
```bash
# 使用.gitignore忽略敏感文件
# 示例.gitignore内容：
api/config.php
*.log
.env
*.key
*.pem
```

### 10.2 使用SSH密钥
```bash
# 生成SSH密钥
ssh-keygen -t rsa -b 4096 -C "your_email@example.com"

# 添加SSH密钥到GitHub
# 复制 ~/.ssh/id_rsa.pub 内容到GitHub设置中

# 使用SSH地址克隆
git clone git@github.com:GoldenApplePie404/PonyConsanguinity_MCServer_Web.git
```

### 10.3 分支保护
- 在GitHub仓库设置中启用分支保护
- 要求Pull Request审查
- 要求状态检查通过
- 限制谁能推送

## 11. 总结

**Git和GitHub开发流程核心要点：**

1. **初始化**：创建仓库、初始化Git、首次提交
2. **日常开发**：修改代码、添加文件、提交、推送
3. **版本控制**：使用标签、分支管理、历史查看
4. **协作开发**：克隆、拉取、推送、解决冲突
5. **最佳实践**：规范提交信息、合理使用分支、定期备份

**记住：Git是一个强大的版本控制工具，掌握基础命令后，你可以逐步学习更高级的功能。多练习、多实践，你会发现Git能大大提高你的开发效率。**

---

*文档更新时间：2026-02-26*
