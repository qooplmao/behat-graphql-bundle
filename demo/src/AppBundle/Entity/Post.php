<?php
/*******************************************************************************
 *  This file is part of the GraphQL Bundle package.
 *
 *  (c) YnloUltratech <support@ynloultratech.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 ******************************************************************************/

namespace Ynlo\GraphQLBundle\Demo\AppBundle\Entity;

use Cocur\Slugify\Slugify;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Ynlo\GraphQLBundle\Annotation as GraphQL;
use Ynlo\GraphQLBundle\Demo\AppBundle\DBAL\Types\PostStatusType;
use Ynlo\GraphQLBundle\Demo\AppBundle\Model\CommentableInterface;
use Ynlo\GraphQLBundle\Demo\AppBundle\Model\CommentableTrait;
use Ynlo\GraphQLBundle\Demo\AppBundle\Model\CommentInterface;
use Ynlo\GraphQLBundle\Demo\AppBundle\Model\HasAuthorInterface;
use Ynlo\GraphQLBundle\Demo\AppBundle\Model\TimestampableInterface;
use Ynlo\GraphQLBundle\Demo\AppBundle\Model\TimestampableTrait;
use Ynlo\GraphQLBundle\Model\NodeInterface;

/**
 * @ORM\Entity()
 * @ORM\Table()
 *
 * @UniqueEntity(fields={"title"}, message="Already exist a post with this title")
 *
 * @ORM\HasLifecycleCallbacks()
 *
 * @GraphQL\ObjectType()
 * @GraphQL\QueryList()
 * @GraphQL\MutationAdd()
 * @GraphQL\MutationUpdate()
 * @GraphQL\MutationDelete()
 * @GraphQL\MutationDeleteBatch()
 */
class Post implements NodeInterface, CommentableInterface, TimestampableInterface, HasAuthorInterface
{
    use TimestampableTrait;
    use CommentableTrait;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string")
     */
    protected $slug;

    /**
     * @var User
     *
     * @Assert\NotNull()
     *
     * @ORM\ManyToOne(targetEntity="Ynlo\GraphQLBundle\Demo\AppBundle\Entity\User", inversedBy="posts")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    protected $author;

    /**
     * @var string[]
     *
     * @ORM\Column(name="tags", type="simple_array", nullable=true)
     */
    protected $tags = [];

    /**
     * @var Collection
     *
     * @ORM\ManyToMany(targetEntity="Ynlo\GraphQLBundle\Demo\AppBundle\Entity\Category", inversedBy="posts")
     *
     * @Assert\NotNull()
     * @Assert\Expression(expression="!this.getCategories().isEmpty()", message="Should have at least one category")
     */
    protected $categories;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="post_status")
     *
     * @Assert\Expression(expression="!this.isFuturePublish() or this.getFuturePublishDate()",
     *     message="A future publish post require a date to publish")
     */
    protected $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="future_publish_date", type="datetime", nullable=true)
     */
    protected $futurePublishDate;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string")
     */
    protected $title;

    /**
     * @var string
     *
     * @ORM\Column(name="body", type="string", nullable=true)
     */
    protected $body;

    /**
     * @var Collection|PostComment[]
     *
     * @ORM\OneToMany(targetEntity="Ynlo\GraphQLBundle\Demo\AppBundle\Entity\PostComment", mappedBy="post", fetch="EXTRA_LAZY")
     */
    protected $comments;

    /**
     * Post constructor.
     */
    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->status = PostStatusType::DRAFT;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return User
     */
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthor(User $author): HasAuthorInterface
    {
        $this->author = $author;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     */
    public function setSlug(string $slug)
    {
        $this->slug = $slug;
    }

    /**
     * @return \string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param \string[] $tags
     */
    public function setTags(array $tags)
    {
        $this->tags = $tags;
    }

    /**
     * @param string $tag
     */
    public function addTag(string $tag)
    {
        $this->tags[] = $tag;
        $this->tags = array_unique($this->tags);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return bool
     */
    public function isPublish()
    {
        return $this->getStatus() === PostStatusType::PUBLISH;
    }

    /**
     * @return bool
     */
    public function isDraft()
    {
        return $this->getStatus() === PostStatusType::DRAFT;
    }

    /**
     * @return bool
     */
    public function isPending()
    {
        return $this->getStatus() === PostStatusType::PENDING;
    }

    /**
     * @return bool
     */
    public function isFuturePublish()
    {
        return $this->getStatus() === PostStatusType::FUTURE;
    }

    /**
     * @return \DateTime
     */
    public function getFuturePublishDate(): ?\DateTime
    {
        return $this->futurePublishDate;
    }

    /**
     * @param \DateTime $futurePublishDate
     */
    public function setFuturePublishDate(\DateTime $futurePublishDate)
    {
        $this->futurePublishDate = $futurePublishDate;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status)
    {
        $this->status = $status;
    }

    /**
     * @param string $title
     *
     * @return Post
     */
    public function setTitle(string $title): Post
    {
        $this->title = $title;
        $this->slug = Slugify::create()->slugify($title);

        return $this;
    }

    /**
     * @return string
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * @param string $body
     *
     * @return Post
     */
    public function setBody(?string $body): Post
    {
        $this->body = $body;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function createComment(): CommentInterface
    {
        $comment = new PostComment();
        $comment->setCommentable($this);

        return $comment;
    }

    /**
     * @return Collection
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    /**
     * @param Collection $categories
     */
    public function setCategories($categories)
    {
        $this->categories = new ArrayCollection($categories);
    }
}
