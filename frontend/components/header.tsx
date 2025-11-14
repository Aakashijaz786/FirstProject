"use client"

interface HeaderProps {
  activeTab: string
  setActiveTab: (tab: string) => void
}

export default function Header({ activeTab, setActiveTab }: HeaderProps) {
  const tabs = [
    { id: "youtube-downloader", label: "Youtube Downloader" },
    { id: "youtube-mp3", label: "Youtube to MP3" },
    { id: "youtube-mp4", label: "Youtube to MP4" },
  ]

  return (
    <header className="border-b border-border">
      <div className="max-w-6xl mx-auto px-4 py-6">
        {/* Logo */}
        <div className="flex items-center justify-center mb-8">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 bg-red-600 rounded flex items-center justify-center">
              <svg className="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" />
              </svg>
            </div>
            <span className="text-xl font-bold text-foreground">YT1s</span>
          </div>
        </div>

        {/* Navigation Tabs */}
        <nav className="flex justify-center gap-8 mb-2">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`pb-4 px-2 text-sm font-medium transition-colors ${
                activeTab === tab.id
                  ? "text-red-600 border-b-2 border-red-600"
                  : "text-muted-foreground hover:text-foreground"
              }`}
            >
              {tab.label}
            </button>
          ))}
        </nav>

        {/* Language Selector */}
        <div className="flex justify-end">
          <button className="text-sm text-muted-foreground hover:text-foreground">English</button>
        </div>
      </div>
    </header>
  )
}
