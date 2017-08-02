# 雪合戦しようぜ！
>目次
>* [Snowball Fightってなんやねん](#whatIsThisShit)
>* [なにこれ、勝手に使ってええん！？](#canIUseThisShit)
>* [どう使うねん、、](#howToUseThisShit)
>   * [導入方法](#howToInstallThisShit)
>   * [コマンドリスト](#shittyCommandsList)
>   * [configの対応値](#config.ini)

## <a name="whatIsThisShit">Snowball Fightってなんやねん
その名の通り、雪合戦です。
チームに参加すると２つのチーム（デフォルトでは赤チームと青チーム）のいずれかに振り分けられ、最小参加人数を超えた状態でしばらく待つ（または権限者が '/sbf start' コマンドを実行する）ことで、参加者はそれぞれのスポーン地点にワープし、雪玉が配布され、ゲームがスタートします。

## <a name="canIUseThisShit">なにこれ、勝手に使ってええん！？
**だーめ！** 一般プレーヤー向けに公開されたサーバーに導入する際は**必ず**[ぼく](https://twitter.com/chell_uoxou)の許可を取った上で使用してください。
でも友達と遊ぶなどといった個人目的で使うのは構いませんよ！

## <a name="howToUseThisShit">どうやって使うん？
**注意：導入してワールドに入ったサバイバルプレーヤーはアイテムが全削除されます！**
### <a name="howToInstallThisShit">導入方法
1. プラグインをダウンロード。
1. [これ](https://github.com/chell-uoxou/BossBarAPI)もダウンロード。
1. サーバーにさっき落としたプラグイン2つを導入。
1. サーバーを起動。
1. 赤チームと青チームそれぞれのスポーン地点を `/sbf edit pos1` コマンドと `/sbf edit pos2` コマンドを用いて設定する。
1. 友達をサーバーに招待してコマンド `/sbf join` を実行させる。（[TapToDo](http://forums.pocketmine.net/plugins/taptodo.170/)とか[CommandSigns](http://forums.pocketmine.net/plugins/commandsigns.958)とかいったプラグインがあると便利！）
1. たぶん勝手に始まるから、楽しんで！

### <a name="shittyCommandsList">コマンドリスト
* `/sbf join` : チームに参加します。試合が進行中の場合はゲームに参加します。
* `/sbf cancel` : チームへの参加をキャンセルします。試合が進行中の場合はゲームからリタイアします。
* `/sbf start` : 試合を強制的に開始します。権限がないと実行できません。
* `/sbf end` : 試合を強制的に終了します。権限がないと実行できません。
* `/sbf add < player name >` : 指定したプレイヤーをチームに参加させます。権限がないと実行できません。
* `/sbf status` : 各チームの参加人数、試合の進行状況など、雪合戦システムのステータスを表示します。権限を必要とするかはconfigで設定できます。
* `/sbf edit <option> <value>` : 雪合戦システムの設定を変更します。現在下記のもののみ対応しています。権限がないと実行できません。
* `/sbf edit pos1` : 自分の立ってる場所をTeamID 1（デフォルトでRed）のスポーン地点に設定します。
* `/sbf edit pos2` : 自分の立ってる場所をTeamID 2（デフォルトでBlue）のスポーン地点に設定します。

### <a name="config.ini">configの対応値
| 値 | 説明 | デフォルト |
|:-----------:|:------------|:----|
| `Prifks:` | 雪合戦プラグインが出力する先頭文字 | SBF |
| `Interval:` | 試合開始から試合終了までの時間 | 600 |
| `WaitInterval:` | ロビーで参加待機中の間隔 | 10 |
| `MinNumOfPeople:` | 試合開始可能になる最少人数 | 2 |
| `MaxNumOfPeople:` | 試合に参加できるの最大人数 | 50 |
| `AllowStatusCommand:` | `/status`コマンドをOP以外に使用可能させるか | false |