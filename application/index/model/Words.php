<?php
namespace app\index\model;
use think\Model;
use think\Db;

class Words extends Model
{
    //获得随机单词列表
    public static function randomSelect($num){
        $str="SELECT * FROM words WHERE id >= ((SELECT MAX(id) FROM words)-(SELECT MIN(id) FROM words)) * RAND() + (SELECT MIN(id) FROM words)  LIMIT ?";

        $list = Db::query($str,[$num]);
        return $list;
    }

    //模糊搜索列表
    public static function likeSelect($en,$num){
        $en = '%%'.$en.'%%';
        $str = "select * from words where en like ? or trans like ? order by tab,en  limit ?";
        return  Db::query($str,[$en,$en,$num]);
    }

    //更新记录表分数
    public static function UpdateRecord($wordid,$userid,$score)
    {
        $str = "insert into record values (DEFAULT,?,?,?,now()) ON DUPLICATE KEY UPDATE score = ? ,lasttime = now()";

        return Db::query($str,[$wordid,$userid,$score,$score]);
    }

    //更新记录表相对分数
    public static function UpdateRelativeRecord($wordid,$userid,$score)
    {
        $str = "insert into record values (DEFAULT,?,?,?,now()) ON DUPLICATE KEY UPDATE score = score + ? ,lasttime = now()";

        return Db::query($str,[$wordid,$userid,$score,$score]);
    }

    public static function getSelectWordListTotal($userid,$order,$level,$tab)
    {
        $order = Common::getOrder($order);

        $level = getClassSql($level);
        $str = "select count(*) as total from words left join record on words.id = record.wordid and userid = ? where ".$level." and tab = ? ";

        $res = Db::query($str,[$userid,$tab]);

        return $res;
    }

    public static function SelectWordList($userid,$order,$start,$lenght,$level,$tab)
    {
        $order = Common::getOrder($order);

        $str = "select 
        words.id as id,
        words.class as class,
        en,
        trans,
        sentence,
        link,
        ifNULL(record.score,0) as score,
        words.tab,
        record.lasttime as relast 
        from words left join record on words.id = record.wordid and userid = ? where ".$level." and tab = ? ORDER BY ".$order." limit ?,?";

        $res = Db::query($str,[$userid,$tab,$start,$lenght]);

        return $res;
    }

    public static function getWordsTotal(){
        return Db::query("select count(*)as total from words")[0]["total"];
    }

    //获得统计分类的单词
    public static function getStatisticClassWords($userid)
    {
        $str = "select 
        count(*) as total,
        sum(if(tab=0,1,0)) as TotalWords,
        sum(if(tab=1,1,0)) as TotalPhrase,
        sum(IF(class%10=1,1,0)) as CEE,
        sum(if(IF(class%10=1,1,0) and record.score > 1,1,0)) as already_CEE,
        sum(IF(class div 10%10=1,1,0)) as CET4,
        sum(if(IF(class div 10%10=1,1,0) and record.score > 1,1,0)) as already_CET4,
        sum(IF(class div 100%10=1,1,0)) as CET6,
        sum(if(IF(class div 100%10=1,1,0) and record.score > 1,1,0)) as already_CET6,
        sum(IF(class div 1000%10=1,1,0)) as PEE,
        sum(if(IF(class div 1000%10=1,1,0) and record.score > 1,1,0)) as already_PEE,
        sum(IF(class div 10000%10=1,1,0)) as SUMMIT,
        sum(if(IF(class div 10000%10=1,1,0) and record.score > 1,1,0)) as already_Summit,
        sum(if(record.score>1,1,0)) as already_Recite 
        from words left join record on words.id = record.wordid and record.userid = ?";
        return DB::query($str,[$userid])[0];
    }

    public static function SelectWordWithUser($en,$userid)
    {
        $str = "select words.id as id,en,trans,sentence,link,class,ifNULL(record.score,0) as score ,lasttime,words.tab from words left join record on words.id = record.wordid and userid = ? where words.en  = ?";

        return Db::query($str,[$userid,$en]);
    }

    public static function SelectWord($en)
    {
        $str = "select * from words where en = ? ";

        return Db::query($str,[$en]);
    }

    //插入新单词或短语
    public static function InsertWord($en,$trans,$sentence,$class,$tab)
    {
        $res = Db::query("insert into words (id,en,trans,sentence,link,class,lastdate,score,tab) values (default,?,?,?,'',?,curdate(),0,?) ON DUPLICATE KEY UPDATE lastdate = curdate()",[$en,$trans,$sentence,$class,$tab]);
    }

    //更新新单词
    public static function UpdateWord($id,$en,$trans,$sentence,$link,$class,$tab)
    {
        $str = "update words set en = ?, trans = ?,sentence= ?,link= ? ,class = ?,tab = ? where id = ?";
        return Db::query($str,[$en,$trans,$sentence,$link,$class,$tab,$id]);
    }

    //更新单词表
    public static function UpdateWordAboutTime($id,$score)
    {
        $str = "update words set score = if(curdate()=lastdate,score+?,score div 2 + 1),lastdate=curdate() where id=?";

        return Db::query($str,[$score,$id]);
    }

    public static function SelectWordScore()
    {
        $str = "select * from words where score>0 order by score desc,lastdate desc,class desc limit 10;";

        return Db::query($str);
    }

    /**
     * 更新单词记忆记录，如果是加分，就将单词的记忆时间更新，如果是负分，只做分数减法，如果记录不存在则插入新的记录。
     */
    //insert into record values (DEFAULT,?,?,?,now()) ON DUPLICATE KEY UPDATE score = ? ,lasttime = now()
    public static function MemorizeWords($userid,$wordid,$score)
    {
        $str = "insert into record values (DEFAULT,?,?,?,now()) ON DUPLICATE KEY update score = score+? ,lasttime=if(?>0,now(),lasttime) ";

        return Db::query($str,[$wordid,$userid,$score,$score,$score]);
    }

    public static function SearchLinkWord($link)
    {
        $linkList = explode(';',$link);
        $link_out = [];
        $index = 0;
        foreach($linkList as $key => $linkword)
        {
            if($linkword == "")
            {
                continue;
            }

            if(substr($linkword,0,1)=='#')
            {

                $res = Words::likeSelect(substr($linkword,1),10);


                foreach($res as $likeRes)
                {
                    $link_out[$index++] = $likeRes;         
                }
            }
            else
            {
                $word = Words::SelectWord($linkword);
                if($word)
                {
                    $link_out[$index++] = $word[0];
                }
            }             
        }
        return $link_out;
    }
}