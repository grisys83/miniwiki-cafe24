# 문법 안내 (Markdown + 위키 링크)

이 문서는 일반 위키 페이지이며, `SyntaxGuide` 제목으로 저장됩니다.

## 헤딩 (Headings)
# H1 제목
## H2 제목
### H3 제목

## 강조 (Emphasis)
- 기울임: *italic*
- 굵게: **bold**
- 굵게+기울임: ***bold italic*** 또는 **_bold italic_**

## 줄바꿈
- 한 문단 안에서 한 줄 개행은 `<br />`로 렌더링됩니다.
- 문단 분리는 빈 줄(공백 라인)로 합니다.

## 링크 (Links)
- 내부 링크(페이지 이동): `[[FrontPage]]`, `[[Home|홈으로]]`
- 통합 Markdown 링크 `[라벨](대상)` 규칙:
  - 대상이 `https://` 또는 `http://`로 시작하면 외부 링크
  - 대상이 `/src`, `/data`, `./...`, `../...` 로 시작하면 경로 링크 그대로 연결
  - 그 외는 내부 페이지 링크로 처리되어 `wiki.php?a=view&title=대상`으로 이동

예)
- 내부: [Home](Home), [Front](FrontPage)
- 외부: [OpenAI](https://openai.com)
- 경로: [/src](/src), [/data](/data)

## 이미지 (Images)
Markdown 이미지 문법을 사용합니다.

```markdown
![Placeholder](https://via.placeholder.com/120)
```

## 목록 (Lists)
- 순서 없는 목록: `-`, `+`, `*`
- 들여쓰기(스페이스 2칸)로 중첩
- 순서 있는 목록: `1.`, `2.` …

```
- 첫째
- 둘째
  - 둘째-하위
1. 하나
2. 둘
```

## 인용 (Blockquote)
`>` 로 시작하는 줄을 사용합니다.

```
> 인용문 1줄
> 인용문 2줄
```

## 코드 (Code)
- 인라인 코드: `inline()` (백틱 사용)
- 펜스드 코드 블록:

```
```
function hello() {
  return 'world';
}
```
```

## 표 (Tables)
Markdown 표 문법을 지원합니다(정렬: `:---`, `---:`, `:---:`).

```
| 열 A | 열 B | 열 C |
| :--- | :---: | ---: |
| left | center | right |
| 1 | 2 | 3 |
```

## 리다이렉트 (Redirect)
페이지를 새 제목으로 넘기려면 본문 맨 위에 다음 한 줄을 넣습니다.

```
#REDIRECT [[새제목]]
```

## 내부 링크 예시
- 자기 참조: [[SyntaxGuide]]
- FrontPage로 이동: [[FrontPage]]
- 라벨 지정: [[Home|홈으로]]

---

이 문서는 `data/pages/SyntaxGuide.md`로 저장되며, 다른 페이지와 동일한 규칙으로 다뤄집니다.

