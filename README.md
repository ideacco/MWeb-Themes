# MWeb-Themes
基于Mweb 驱动创建的模板样式。

---


## Myidea 模板
![image](https://github.com/ideacco/MWeb-Themes/blob/master/document/myidea.jpg)

idea是基于pinghsu模板的MWEB移植模板。适合项目文档管理，也非常适合做个人博客，并且搭载了开源评论系统hashover。

---

Myidea是基于pinghsu模板的MWEB移植模板，拥有pinghsu模板的大部分功能，包括：

```
1.自定义ICON，支持十种个性化徽标。
2.自定义首页POST颜色。
3.自定义POST展示缩略图。
4.支持文章标签导航。
5.支持Disqus和开源评论系统HashOver（PHP系统）
6.文章归档按照文件夹（分类）而不是按照时间分类。更适合做项目的存档管理。
```


---
## Myidea 主题的使用方法

1. 首先下载模板文件到电脑上，并且把文件放到模板文件夹中。
ps：在Mweb中左侧的文件夹上点击右键，并且选择编辑--在弹出的窗口中选择--在Finder中打开。
2. 在Mweb中使用（选择）Myidea这个模板。
3. 在高级设置中---网站扩展中导入extensions文件夹中的 Site_Extension.json 文件
4. 在高级设置中---文章扩展中导入extensions文件夹中的 Document_Extension.json 文件
5. 在Mweb编辑好文章，点击右上角的圆形按钮（文档大纲），在弹出的选项卡中，根据需要的变量
6. post image： 就直接复制文章内部的连接 （图片&附件 --- Copy按钮）比如：media/15065694615888/15890194011094.jpg
7. post icon：这个是设置文章列表上预览图的icon的，可选icon（共10个）有提示。
8. post color：这个是设置文章列表上的预览图的叠加颜色的，根据提示设置。

```
网站扩展变量说明：
Author  作者
back-image  背景图
Disqus 评论系统

文章扩展变量说明：
post image 文章标题小图
post icon 文章个性徽标
post colour 文章标题颜色
```

### Myidea主题的更多预览
根据标签分类：可以检索文件夹内的子文件名称，以文件夹名称分类。
![image](https://github.com/ideacco/MWeb-Themes/blob/master/document/myidea2.png)

翻页按钮以文章名称命名，并且增加了版权说明
![image](https://github.com/ideacco/MWeb-Themes/blob/master/document/myidea3.png)

正文增加了右侧的悬浮文章导航条
![image](https://github.com/ideacco/MWeb-Themes/blob/master/document/myidea4.png)


---
注：
pinghsu https://www.linpx.com/p/pinghsu-a-typecho-theme.html ；https://github.com/chakhsu/pinghsu

hashover 开源评论系统 http://tildehash.com/?page=hashover ；https://github.com/jacobwb/hashover-next
